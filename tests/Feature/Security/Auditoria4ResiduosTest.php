<?php

/**
 * 4ª auditoria — VERIFICAÇÃO FINAL: os três resíduos BAIXOS que sobraram depois de
 * fechados os 9 achados (nenhum deles com caminho vivo cross-tenant, todos medidos por
 * sonda executada). Cada teste aqui é a sonda INVERTIDA: o ataque roda e o teste passa
 * quando o ataque FALHA — sempre com um controle positivo do uso legítimo ao lado.
 *
 *  - COMPRAS-9 (sonda P4-T4): `GET /admin/papeis`, a tela de papéis da FUNDAÇÃO, era a
 *    única rota de negócio fora do grupo completo — tinha tudo menos `feature:compras`.
 *    Tenant sem o entitlement 'compras' ainda abria a governança de RBAC deste app.
 *  - COMPRAS-6 (sonda P4-U8, HTTP=403 policy.decidir(sem middleware)=true): a
 *    AprovacaoPolicy não olhava o STATUS do vínculo. Suspenso na empresa, mas com
 *    `unidade_user` vivo, recebia `true` da policy chamada DIRETO. Pela web o
 *    `tenant.ativo` barrava; qualquer job/comando futuro herdaria o buraco.
 *  - COMPRAS-4r (sondas R4-N4 e R4-N5): o flood ilimitado já estava fechado, mas o teto
 *    era um balde SÓ. Três mensagens de remetentes estranhos carregando a referência
 *    pública [COT-ULID] consumiam a cota do dia e CALAVAM por 24h o aviso do fornecedor
 *    verdadeiro que responde sem SPF/DKIM; e três propostas incoerentes pelo link
 *    assinado trancavam o link por 24h, inclusive para a proposta COERENTE seguinte.
 */

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// ─── COMPRAS-9 — gate de feature em TODA rota de negócio ─────────────────────

/**
 * Rotas que ficam FORA do grupo completo por DESENHO (a sonda P4-T4 as lista junto com
 * as de negócio; só estas são legítimas):
 *  - identidade/sessão da fundação: quem ainda não entrou, ou está entrando, não tem
 *    tenant ativo nem 2FA resolvido (login, desafio, logout, troca de senha/tenant);
 *  - links públicos de posse: convite, redefinição de senha, confirmação de e-mail e a
 *    proposta pública da cotação (esta com `throttle:cotacao-link`);
 *  - integração server-to-server com HMAC (inbound de eventos, webhook do provedor);
 *  - infraestrutura que não é rota de negócio: health check, assets do Livewire,
 *    storage, sanctum e o endpoint de log do Boost (dev).
 */
const A4R_ROTAS_FORA_DO_GRUPO = [
    '/',
    'login', '2fa/desafio', 'logout', 'senha/trocar', 'tenant/trocar/{tenant}', 'seguranca/passkeys',
    'convite/{token}', 'senha/redefinir/{token}', 'email/confirmar/{token}',
    'cotacao/proposta/{token}',
    'api/inbound/events', 'hooks/channels/{credential}',
    'up', 'sanctum/csrf-cookie', 'storage/{path}',
];

it('COMPRAS-9: nenhuma rota de negócio fica fora do grupo completo — /admin/papeis inclusive', function () {
    $obrigatorios = ['auth', 'ativo', 'tenant.ctx', 'tenant.ativo', 'troca.senha', '2fa.enforce', 'feature:compras'];

    $fora = [];
    foreach (Route::getRoutes() as $rota) {
        $uri = $rota->uri();
        if (in_array($uri, A4R_ROTAS_FORA_DO_GRUPO, true) || str_starts_with($uri, 'livewire') || str_starts_with($uri, '_boost/')) {
            continue;
        }

        $faltando = array_values(array_diff($obrigatorios, $rota->gatherMiddleware()));
        if ($faltando !== []) {
            $fora[] = $uri.' => falta: '.implode(',', $faltando);
        }
    }

    expect($fora)->toBe([]);

    // E a rota existe UMA vez só (o registro do pacote está desligado em
    // config('foundation.tenant_admin_routes')) — senão o primeiro match venceria.
    expect(config('foundation.tenant_admin_routes'))->toBeFalse()
        ->and(collect(Route::getRoutes())->filter(fn ($r) => $r->uri() === 'admin/papeis')->count())->toBe(1)
        ->and(Route::getRoutes()->getByName('admin.papeis')->gatherMiddleware())->toContain('feature:compras', 'admin');
});

it('COMPRAS-9: admin de tenant SEM o entitlement compras não abre /admin/papeis (e com o entitlement, abre)', function () {
    // ATAQUE: a empresa não assinou o Compras, mas o admin dela tenta a tela de papéis.
    TenantContext::forget();
    $semEntitlement = Tenant::create(['slug' => 'sem-compras-a4r', 'name' => 'Sem Compras', 'status' => 'active']);
    $adminSem = User::factory()->admin()->create(['tenant_id' => $semEntitlement->id, 'email' => 'admin-sem@a4r.test']);

    expect($semEntitlement->fresh()->hasFeature('compras'))->toBeFalse();
    $this->actingAs($adminSem)->get('/admin/papeis')->assertForbidden();
    $this->actingAs($adminSem)->get('/admin/canais')->assertForbidden();

    // CONTROLE POSITIVO: o mesmo admin, na empresa COM o entitlement, abre normalmente.
    $comEntitlement = Tenant::create(['slug' => 'com-compras-a4r', 'name' => 'Com Compras', 'status' => 'active']);
    $comEntitlement->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    $adminCom = User::factory()->admin()->create(['tenant_id' => $comEntitlement->id, 'email' => 'admin-com@a4r.test']);

    TenantContext::set($comEntitlement->id);
    // A sessão do teste é a mesma das requisições acima (que fixaram o tenant sem
    // entitlement como ativo): o tenant da empresa certa é declarado.
    $this->actingAs($adminCom)->withSession(['active_tenant_id' => $comEntitlement->id])
        ->get('/admin/papeis')->assertOk();
});

// ─── COMPRAS-6 — a policy exige vínculo ATIVO, sem depender do middleware ────

function a4r_requisicaoComEtapa(Unidade $unidade, NivelAlcada $nivel): Requisicao
{
    $solicitante = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true]);

    $requisicao = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-A4R-'.fake()->unique()->numerify('######'),
    ]);
    Aprovacao::create([
        'requisicao_id' => $requisicao->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
        'nivel_exigido' => $nivel->value, 'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    return $requisicao;
}

it('COMPRAS-6: aprovador SUSPENSO na empresa, com unidade_user vivo, não passa nem na policy chamada DIRETO', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = a4r_requisicaoComEtapa($unidade, NivelAlcada::Gestor);
    $aprovador = User::factory()->create(['email' => 'suspenso@a4r.test']);
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    // CONTROLE POSITIVO: com o vínculo ATIVO, a policy aprova (a regra não mudou).
    expect($aprovador->can('aprovacao.acessar', $requisicao))->toBeTrue()
        ->and($aprovador->can('aprovacao.decidir', $requisicao))->toBeTrue();

    // ATAQUE: suspenso na empresa, mas o vínculo com a unidade continua vivo.
    DB::table('tenant_user')->where('user_id', $aprovador->id)->update(['status' => 'suspended']);
    expect(DB::table('unidade_user')->where('user_id', $aprovador->id)->count())->toBe(1);

    $suspenso = $aprovador->fresh();
    expect($suspenso->can('aprovacao.acessar', $requisicao))->toBeFalse()
        ->and($suspenso->can('aprovacao.decidir', $requisicao))->toBeFalse();

    // Regressão da porta web (a sonda media HTTP=403): continua barrado lá também.
    $this->actingAs($suspenso)->get('/aprovacoes/'.$requisicao->id)->assertStatus(403);
});

it('COMPRAS-6: nem o ADMIN suspenso decide — o bypass de admin vem depois do vínculo', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = a4r_requisicaoComEtapa($unidade, NivelAlcada::Gestor);
    $admin = User::factory()->admin()->create(['email' => 'admin-susp@a4r.test']);
    // Com vínculo de aprovador vivo na unidade: sem ele, o admin suspenso já cairia pelo
    // próprio `isAdminForActiveTenant` (que lê o pivot ATIVO) e o teste não provaria nada.
    $admin->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    expect($admin->can('aprovacao.decidir', $requisicao))->toBeTrue();

    DB::table('tenant_user')->where('user_id', $admin->id)->update(['status' => 'suspended']);

    expect($admin->fresh()->can('aprovacao.decidir', $requisicao))->toBeFalse();
});

// ─── COMPRAS-4r — baldes separados: estranho não cala o fornecedor ───────────

/** @return array{cotacao: Cotacao, link: CotacaoLink, url: string, item: ItemRequisicao, fornecedor: Fornecedor} */
function a4r_cotacaoAberta(array $estimado = ['quantidade' => 2, 'valor_unitario_estimado' => 10]): array
{
    $compradora = User::factory()->create(['email' => 'comp-'.uniqid().'@a4r.test']);
    $unidade = Unidade::factory()->create();
    $requisicao = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'centro_custo_id' => CentroCusto::factory()->create(['unidade_id' => $unidade->id])->id,
        'solicitante_id' => $compradora->id,
        'status' => StatusRequisicao::EmCotacao,
        'codigo' => 'REQ-A4R-'.fake()->unique()->numerify('######'),
    ]);
    $item = ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, ...$estimado]);
    $fornecedor = Fornecedor::factory()->homologado()->create(['contato_email' => 'real-'.uniqid().'@forn.test']);
    $cotacao = Cotacao::factory()->create([
        'requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'criada_por' => $compradora->id, 'valor' => null,
    ]);
    $emitido = app(CotacaoLinkService::class)->emitir($cotacao, now()->addDays(3));

    return ['cotacao' => $cotacao, 'link' => $emitido['link'], 'url' => $emitido['url'], 'item' => $item, 'fornecedor' => $fornecedor];
}

function a4r_email(array $c, string $de, string $id, ?string $autenticacao = null): MensagemEmail
{
    return new MensagemEmail($id, "<{$id}@origem>", $de, 'RE: Cotação [COT-'.$c['link']->referencia.']', 'segue', autenticacao: $autenticacao);
}

it('COMPRAS-4r: flood de estranhos NÃO cala o fornecedor verdadeiro que responde sem SPF/DKIM', function () {
    Mail::fake();
    $c = a4r_cotacaoAberta();
    $acao = app(ProcessarRespostaCotacaoAction::class);
    $avisos = fn () => Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count();

    // ATAQUE: 3 estranhos gastam o balde do dia com a referência PÚBLICA [COT-ULID].
    for ($i = 0; $i < 3; $i++) {
        $acao->execute(a4r_email($c, "atacante{$i}@evil.test", "flood{$i}"));
    }
    $depoisDoFlood = $avisos();
    expect($depoisDoFlood)->toBe(3);

    // O ataque FALHA: o fornecedor do cadastro, SEM autenticação (domínio sem SPF/DKIM),
    // continua sendo avisado — a sonda R4-N4 media 0 aqui.
    $avisado = $acao->execute(a4r_email($c, $c['fornecedor']->contato_email, 'forn-sem-auth'));
    expect($avisado)->not->toBeNull()
        ->and($avisos())->toBe($depoisDoFlood + 1);

    // CONTROLE POSITIVO 1: o balde do fornecedor AUTENTICADO segue intocado por tudo isso.
    config(['mail.imap.authserv_id' => 'mx.helix.test']);
    $de = $c['fornecedor']->contato_email;
    $dominio = substr((string) strrchr($de, '@'), 1);
    $acao->execute(a4r_email($c, $de, 'forn-auth', "mx.helix.test; spf=pass smtp.mailfrom={$de}; dkim=pass header.d={$dominio}; dmarc=pass header.from={$dominio}"));
    expect($avisos())->toBe($depoisDoFlood + 2);

    // CONTROLE POSITIVO 2: o teto que fechou o flood continua de pé — mais 25 estranhos
    // (remetentes distintos) não emitem NENHUM aviso a mais.
    for ($i = 0; $i < 25; $i++) {
        $acao->execute(a4r_email($c, "bot{$i}@evil.test", "rot{$i}"));
    }
    expect($avisos())->toBe($depoisDoFlood + 2);
});

it('COMPRAS-4r: o balde do remetente do cadastro sem autenticação também tem teto (From forjado não inunda)', function () {
    Mail::fake();
    $c = a4r_cotacaoAberta();
    $acao = app(ProcessarRespostaCotacaoAction::class);

    // 25 mensagens com o From do fornecedor e SEM autenticação: podem ser forjadas.
    for ($i = 0; $i < 25; $i++) {
        $acao->execute(a4r_email($c, $c['fornecedor']->contato_email, "forjado{$i}"));
    }

    expect(Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count())
        ->toBe(config('compras.cotacao_email.avisos_do_fornecedor_sem_autenticacao_por_cotacao_dia'));
});

it('COMPRAS-4r: 3 propostas incoerentes de UMA origem não trancam o link para a proposta coerente de OUTRA', function () {
    $c = a4r_cotacaoAberta();
    $incoerente = ['precos' => [$c['item']->id => '5000']];   // 5.000 × 2 = 10.000 > 100 × 20
    $coerente = ['precos' => [$c['item']->id => '12']];
    $de = fn (string $ip) => ['REMOTE_ADDR' => $ip];

    // ATAQUE: quem tem o link floda incoerências de uma origem só.
    for ($i = 0; $i < 3; $i++) {
        $this->call('POST', $c['url'], $incoerente, [], [], $de('203.0.113.10'))->assertStatus(302);
    }

    // A barreira anti-sondagem continua valendo PARA ELE: nem a proposta coerente passa.
    $this->call('POST', $c['url'], $coerente, [], [], $de('203.0.113.10'))->assertStatus(302);
    expect(DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBeNull();

    // O ataque FALHA: o fornecedor legítimo, de outra origem, manda a proposta coerente no
    // MESMO dia — a sonda R4-N5 media o link trancado por 24h para todo mundo.
    $this->call('POST', $c['url'], $coerente, [], [], $de('198.51.100.20'))->assertOk();
    expect((float) DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBe(24.0)
        ->and(DB::table('cotacao_links')->where('id', $c['link']->id)->value('submetido_em'))->not->toBeNull();
});

it('COMPRAS-4r: o teto GLOBAL por link continua barrando a sondagem do orçamento por tentativa e erro', function () {
    config(['compras.proposta_publica.tentativas_incoerentes_por_link_dia' => 4]);
    $c = a4r_cotacaoAberta();
    $incoerente = ['precos' => [$c['item']->id => '5000']];

    // Quatro incoerências espalhadas por origens diferentes (cada uma dentro do balde dela).
    foreach (['203.0.113.1', '203.0.113.2', '203.0.113.3', '203.0.113.4'] as $ip) {
        $this->call('POST', $c['url'], $incoerente, [], [], ['REMOTE_ADDR' => $ip])->assertStatus(302);
    }

    // Estourado o teto do LINK, nem uma origem virgem passa — inclusive com valor coerente.
    $this->call('POST', $c['url'], ['precos' => [$c['item']->id => '12']], [], [], ['REMOTE_ADDR' => '203.0.113.99'])->assertStatus(302);
    expect(DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBeNull();
});

it('COMPRAS-4r: os limites dos baldes vêm de config LITERAL', function () {
    $config = require base_path('config/compras.php');

    expect($config['cotacao_email']['avisos_por_remetente_dia'])->toBe(1)
        ->and($config['cotacao_email']['avisos_de_estranhos_por_cotacao_dia'])->toBe(3)
        ->and($config['cotacao_email']['avisos_do_fornecedor_sem_autenticacao_por_cotacao_dia'])->toBe(3)
        ->and($config['cotacao_email']['avisos_do_fornecedor_por_cotacao_dia'])->toBe(5)
        ->and($config['proposta_publica']['tentativas_incoerentes_por_origem_dia'])->toBe(3)
        ->and($config['proposta_publica']['tentativas_incoerentes_por_link_dia'])->toBe(10);
});
