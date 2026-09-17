<?php

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\GestaoCotacoes;
use App\Mail\RespostaCotacaoRecebida;
use App\Mail\SolicitacaoCotacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
| Decisão 11 — resposta de cotação por LINK ASSINADO (parecer OWASP ASVS).
|
| O link é o único canal que grava proposta. Cada teste abaixo derruba uma trava
| específica (assinatura, hash do token, uso único, expiração, revogação, tenant,
| fornecedor, rate limit, ausência de oráculo) e prova o COMPORTAMENTO recusado.
*/

beforeEach(function () {
    Mail::fake();
    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'bravo-link', 'name' => 'Bravo', 'status' => 'active'])->id;
    Tenant::find($this->tenantB)->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    TenantContext::forget();
});

/**
 * Cotação aguardando (2 itens) com link emitido no tenant informado.
 *
 * @return array{cotacao: Cotacao, link: CotacaoLink, url: string, token: string, itens: Collection<int, ItemRequisicao>}
 */
function cenarioLink(string $tenantId, string $prazo = '+3 days'): array
{
    return TenantContext::runFor($tenantId, function () use ($prazo, $tenantId) {
        $compradora = User::factory()->create(['tenant_id' => $tenantId, 'email' => 'compradora-'.uniqid().'@helix.test']);
        $unidade = Unidade::factory()->create();
        $requisicao = Requisicao::factory()->create([
            'unidade_id' => $unidade->id,
            'centro_custo_id' => CentroCusto::factory()->create(['unidade_id' => $unidade->id])->id,
            'solicitante_id' => $compradora->id,
            'status' => StatusRequisicao::EmCotacao,
            'codigo' => 'REQ-LNK-'.fake()->unique()->numerify('#####'),
        ]);
        $itens = collect([
            ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 2, 'descricao' => 'Cimento CP-II']),
            ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 10, 'descricao' => 'Areia média']),
        ]);
        $fornecedor = Fornecedor::factory()->homologado()->create(['contato_email' => 'forn-'.uniqid().'@alfa.test']);
        $cotacao = Cotacao::factory()->create([
            'requisicao_id' => $requisicao->id,
            'fornecedor_id' => $fornecedor->id,
            'criada_por' => $compradora->id,
            'valor' => null,
        ]);

        $emitido = app(CotacaoLinkService::class)->emitir($cotacao, now()->modify($prazo));

        return [
            'cotacao' => $cotacao,
            'link' => $emitido['link'],
            'url' => $emitido['url'],
            'token' => tokenDaUrl($emitido['url']),
            'itens' => $itens,
        ];
    });
}

function tokenDaUrl(string $url): string
{
    return basename((string) parse_url($url, PHP_URL_PATH));
}

/** Payload de proposta válido para os itens do cenário. */
function propostaValida(array $cenario): array
{
    return [
        'precos' => [$cenario['itens'][0]->id => '30.00', $cenario['itens'][1]->id => '5.50'],
        'prazo_entrega_dias' => 7,
        'observacoes' => 'Entrega em obra.',
    ];
}

/** Estado da cotação lido SEM contexto (o teste roda como plataforma). */
function cotacaoCrua(int $id): Cotacao
{
    return Cotacao::withoutTenantScope()->whereKey($id)->firstOrFail();
}

// ─── Caminho feliz ───────────────────────────────────────────────────────────

it('guarda só o SHA-256 do token de 256 bits e carimba tenant, cotação e fornecedor', function () {
    $c = cenarioLink($this->tenantA);

    $bruto = DB::table('cotacao_links')->where('id', $c['link']->id)->first();

    expect(strlen(rtrim(strtr($c['token'], '-_', '+/'), '=')))->toBeGreaterThanOrEqual(43)
        ->and(strlen(base64_decode(strtr($c['token'], '-_', '+/'))))->toBe(32)
        ->and($bruto->token_hash)->toBe(hash('sha256', $c['token']))
        ->and(json_encode($bruto))->not->toContain($c['token'])
        ->and($bruto->tenant_id)->toBe($this->tenantA)
        ->and((int) $bruto->cotacao_id)->toBe($c['cotacao']->id)
        ->and((int) $bruto->fornecedor_id)->toBe($c['cotacao']->fornecedor_id)
        ->and($c['url'])->toContain('signature=');
});

it('abre o formulário quantas vezes for preciso sem consumir o link', function () {
    $c = cenarioLink($this->tenantA);

    $this->get($c['url'])->assertOk()->assertSee('Cimento CP-II');
    $this->get($c['url'])->assertOk();

    expect(CotacaoLink::withoutTenantScope()->whereKey($c['link']->id)->value('submetido_em'))->toBeNull();
});

it('grava a proposta como sugestão, consome o link, audita IP/UA e avisa a compradora', function () {
    $c = cenarioLink($this->tenantA);

    $this->withHeader('User-Agent', 'NavegadorFornecedor/1.0')
        ->post($c['url'], propostaValida($c))
        ->assertOk()
        ->assertSee('Proposta recebida');

    $cot = cotacaoCrua($c['cotacao']->id);
    // 2 × 30,00 + 10 × 5,50 = 115,00 — o valor OFICIAL continua null (compradora confirma).
    expect((float) $cot->valor_respondido)->toBe(115.00)
        ->and($cot->valor)->toBeNull()
        ->and($cot->prazo_respondido)->toBe(7)
        ->and($cot->resposta_recebida_em)->not->toBeNull()
        ->and(DB::table('itens_cotacao')->where('cotacao_id', $cot->id)->count())->toBe(2)
        ->and(CotacaoLink::withoutTenantScope()->whereKey($c['link']->id)->value('submetido_em'))->not->toBeNull();

    $audit = DB::table('audit_logs')->where('action', 'compras.cotacao_proposta_recebida')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->tenant_id)->toBe($this->tenantA)
        ->and($audit->ip_address)->not->toBeNull()
        ->and($audit->user_agent)->toBe('NavegadorFornecedor/1.0');

    Mail::assertSent(RespostaCotacaoRecebida::class);
});

it('descarta preço de item que não pertence à requisição da cotação', function () {
    $c = cenarioLink($this->tenantA);
    $alheio = cenarioLink($this->tenantA);

    $payload = propostaValida($c);
    $payload['precos'][$alheio['itens'][0]->id] = '999.00';

    $this->post($c['url'], $payload)->assertOk();

    expect(DB::table('itens_cotacao')->where('cotacao_id', $c['cotacao']->id)->pluck('item_requisicao_id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toBe($c['itens']->pluck('id')->sort()->values()->all());
});

// ─── Uso único / expiração / revogação ───────────────────────────────────────

it('recusa reuso após a submissão (nem visualiza, nem regrava)', function () {
    $c = cenarioLink($this->tenantA);

    $this->post($c['url'], propostaValida($c))->assertOk();

    $this->get($c['url'])->assertNotFound()->assertSee('Link indisponível');
    $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '1.00']])->assertNotFound();

    expect((float) cotacaoCrua($c['cotacao']->id)->valor_respondido)->toBe(115.00);
});

it('recusa o link cujo prazo venceu na base, mesmo com a assinatura ainda válida', function () {
    $c = cenarioLink($this->tenantA);
    TenantContext::runFor($this->tenantA, fn () => CotacaoLink::query()->whereKey($c['link']->id)->update(['expires_at' => now()->subMinute()]));

    $this->get($c['url'])->assertNotFound();
    $this->post($c['url'], propostaValida($c))->assertNotFound();

    expect(cotacaoCrua($c['cotacao']->id)->valor_respondido)->toBeNull();
});

it('recusa a assinatura expirada, mesmo com o link válido na base', function () {
    $c = cenarioLink($this->tenantA);
    $urlVencida = URL::temporarySignedRoute('cotacao.proposta', now()->subMinute(), ['token' => $c['token']]);

    $this->get($urlVencida)->assertNotFound();
    $this->post($urlVencida, propostaValida($c))->assertNotFound();

    expect(cotacaoCrua($c['cotacao']->id)->valor_respondido)->toBeNull();
});

it('expira pelo prazo da cotação (viagem no tempo)', function () {
    $c = cenarioLink($this->tenantA, '+1 day');

    $this->travel(3)->days();

    $this->get($c['url'])->assertNotFound();
});

it('link revogado não abre; reenviar revoga o anterior e o novo funciona', function () {
    $c = cenarioLink($this->tenantA);

    TenantContext::runFor($this->tenantA, fn () => app(CotacaoLinkService::class)->revogar($c['cotacao']));
    $this->get($c['url'])->assertNotFound();

    $novo = TenantContext::runFor($this->tenantA, fn () => app(CotacaoLinkService::class)->emitir($c['cotacao'], now()->addDays(2)));
    $this->get($novo['url'])->assertOk();

    $outro = TenantContext::runFor($this->tenantA, fn () => app(CotacaoLinkService::class)->emitir($c['cotacao'], now()->addDays(2)));
    $this->get($novo['url'])->assertNotFound();
    $this->get($outro['url'])->assertOk();
});

// ─── Adulteração / tenant / fornecedor ───────────────────────────────────────

it('recusa token adulterado (assinatura quebrada) e token desconhecido com assinatura válida', function () {
    $c = cenarioLink($this->tenantA);

    $adulterado = str_replace($c['token'], substr($c['token'], 0, -1).(str_ends_with($c['token'], 'A') ? 'B' : 'A'), $c['url']);
    $this->get($adulterado)->assertNotFound();

    // Assinatura legítima do APP para um token que não existe na base: só o hash recusa.
    $forjado = URL::temporarySignedRoute('cotacao.proposta', now()->addDay(), ['token' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')]);
    $this->get($forjado)->assertNotFound();
    $this->post($forjado, propostaValida($c))->assertNotFound();

    // Assinatura removida/alterada com o token verdadeiro.
    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $c['url']))->assertNotFound();

    expect(cotacaoCrua($c['cotacao']->id)->valor_respondido)->toBeNull();
});

it('link de OUTRO tenant não alcança a cotação apontada (cotacao_id adulterado)', function () {
    // 4ª auditoria (COMPRAS-5): o banco hoje RECUSA esta linha cruzada (FK composta
    // cotacao_links → cotacoes/fornecedores; ver Auditoria4CotacaoCanalPublicoTest). Este
    // teste segue valendo para a SEGUNDA camada — a aplicação — e por isso reproduz o estado
    // legado derrubando a FK. DDL no meio do teste só isola em SQLite (no MySQL faz commit
    // implícito e fura o RefreshDatabase); lá a linha cruzada nem chega a existir.
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Linha cruzada é impossível com a FK composta; o cenário legado só é reproduzível em SQLite.');
    }
    (require database_path('migrations/2026_09_22_000001_fk_composta_cotacao_links_e_unidade_user_para_a_mae.php'))->down();

    $a = cenarioLink($this->tenantA);
    $b = cenarioLink($this->tenantB);

    // O link do tenant B passa a apontar para a cotação do tenant A (restore/bug/injeção).
    TenantContext::runFor($this->tenantB, fn () => CotacaoLink::query()->whereKey($b['link']->id)->update([
        'cotacao_id' => $a['cotacao']->id,
        'fornecedor_id' => $a['cotacao']->fornecedor_id,
    ]));

    $this->get($b['url'])->assertNotFound();
    $this->post($b['url'], propostaValida($a))->assertNotFound();

    expect(cotacaoCrua($a['cotacao']->id)->valor_respondido)->toBeNull()
        ->and(DB::table('itens_cotacao')->where('cotacao_id', $a['cotacao']->id)->count())->toBe(0);
});

it('link emitido para OUTRO fornecedor não grava na cotação', function () {
    $c = cenarioLink($this->tenantA);

    TenantContext::runFor($this->tenantA, function () use ($c) {
        $outro = Fornecedor::factory()->homologado()->create();
        CotacaoLink::query()->whereKey($c['link']->id)->update(['fornecedor_id' => $outro->id]);
    });

    $this->get($c['url'])->assertNotFound();
    $this->post($c['url'], propostaValida($c))->assertNotFound();

    expect(cotacaoCrua($c['cotacao']->id)->valor_respondido)->toBeNull();
});

it('recusa cotação já confirmada ou requisição fora de cotação', function () {
    $confirmada = cenarioLink($this->tenantA);
    $fechada = cenarioLink($this->tenantA);

    TenantContext::runFor($this->tenantA, function () use ($confirmada, $fechada) {
        Cotacao::query()->whereKey($confirmada['cotacao']->id)->update(['valor' => 10]);
        Requisicao::withoutGlobalScope(UnidadeScope::class)->whereKey($fechada['cotacao']->requisicao_id)
            ->update(['status' => StatusRequisicao::CotacaoConcluida]);
    });

    $this->post($confirmada['url'], propostaValida($confirmada))->assertNotFound();
    $this->post($fechada['url'], propostaValida($fechada))->assertNotFound();

    expect(cotacaoCrua($confirmada['cotacao']->id)->valor_respondido)->toBeNull()
        ->and(cotacaoCrua($fechada['cotacao']->id)->valor_respondido)->toBeNull();
});

it('não é oráculo: todo link recusado devolve a MESMA resposta', function () {
    $usado = cenarioLink($this->tenantA);
    $this->post($usado['url'], propostaValida($usado))->assertOk();

    $revogado = cenarioLink($this->tenantA);
    TenantContext::runFor($this->tenantA, fn () => app(CotacaoLinkService::class)->revogar($revogado['cotacao']));

    $expirado = cenarioLink($this->tenantA);
    TenantContext::runFor($this->tenantA, fn () => CotacaoLink::query()->whereKey($expirado['link']->id)->update(['expires_at' => now()->subMinute()]));

    $respostas = collect([
        'usado' => $usado['url'],
        'revogado' => $revogado['url'],
        'expirado' => $expirado['url'],
        'desconhecido' => URL::temporarySignedRoute('cotacao.proposta', now()->addDay(), ['token' => 'nao-existe']),
        'sem assinatura' => route('cotacao.proposta', ['token' => $expirado['token']]),
    ])->map(function (string $url) {
        $r = $this->get($url);

        return $r->getStatusCode().'|'.$r->getContent();
    });

    expect($respostas->unique())->toHaveCount(1)
        ->and($respostas->first())->toStartWith('404|');
});

// ─── Rate limit ──────────────────────────────────────────────────────────────

it('limita tentativas por token', function () {
    $c = cenarioLink($this->tenantA);

    foreach (range(1, 10) as $_) {
        $this->get($c['url'])->assertOk();
    }

    $this->get($c['url'])->assertStatus(429);
});

it('limita tentativas por IP (varredura de tokens)', function () {
    foreach (range(1, 30) as $i) {
        $this->get(route('cotacao.proposta', ['token' => 'varredura-'.$i]))->assertNotFound();
    }

    $this->get(route('cotacao.proposta', ['token' => 'varredura-31']))->assertStatus(429);
});

// ─── Envio pela tela da compradora ───────────────────────────────────────────

it('a solicitação por e-mail envia o link assinado e o reenvio revoga o anterior', function () {
    TenantContext::runFor($this->tenantA, function () {
        $compradora = User::factory()->compradora()->create();
        $unidade = Unidade::factory()->create();
        $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
        $requisicao = Requisicao::factory()->create([
            'unidade_id' => $unidade->id,
            'centro_custo_id' => CentroCusto::factory()->create(['unidade_id' => $unidade->id])->id,
            'status' => StatusRequisicao::EmCotacao,
            'codigo' => 'REQ-LNK-UI',
        ]);
        $fornecedor = Fornecedor::factory()->homologado()->create(['contato_email' => 'ui@alfa.test']);

        $tela = Livewire::actingAs($compradora)->test(GestaoCotacoes::class, ['id' => $requisicao->id])
            ->set('fornecedoresSolicitar', [$fornecedor->id])
            ->set('prazoResposta', now()->addDays(5)->toDateString())
            ->call('solicitarPorEmail')
            ->assertHasNoErrors();

        $urls = [];
        Mail::assertSent(SolicitacaoCotacao::class, function (SolicitacaoCotacao $m) use (&$urls) {
            $urls[] = $m->url;

            return $m->hasTo('ui@alfa.test') && str_contains($m->envelope()->subject, '[COT-'.$m->referencia.']');
        });

        $cotacao = Cotacao::query()->where('requisicao_id', $requisicao->id)->firstOrFail();
        $link = CotacaoLink::query()->where('cotacao_id', $cotacao->id)->firstOrFail();
        expect($link->token_hash)->toBe(hash('sha256', tokenDaUrl($urls[0])))
            ->and($link->expires_at->toDateString())->toBe(now()->addDays(5)->toDateString())
            ->and($link->criado_por)->toBe($compradora->id);

        $tela->call('reenviarLink', $cotacao->id)->assertHasNoErrors();

        expect(CotacaoLink::query()->where('cotacao_id', $cotacao->id)->count())->toBe(2)
            ->and($link->fresh()->revogado_em)->not->toBeNull()
            ->and(CotacaoLink::query()->where('cotacao_id', $cotacao->id)->whereNull('revogado_em')->count())->toBe(1);

        $tela->call('revogarLink', $cotacao->id);
        expect(CotacaoLink::query()->where('cotacao_id', $cotacao->id)->whereNull('revogado_em')->count())->toBe(0);
    });
});
