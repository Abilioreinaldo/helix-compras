<?php

/**
 * 4ª auditoria adversarial — COMPRAS-4, COMPRAS-5 e COMPRAS-8 (BAIXOS), todos no canal
 * público da cotação (link assinado + caixa IMAP).
 *
 *  - COMPRAS-4 (sonda P4-I2): a referência pública `[COT-ULID]` deixava QUALQUER remetente
 *    disparar avisos ilimitados ao comprador (25/25), com a marca Helix.
 *  - COMPRAS-5 (sondas P4-L6 e P4-U6): o banco ACEITAVA `cotacao_links` do tenant B
 *    apontando para cotação do tenant A, e `unidade_user` do tenant A com unidade de B.
 *  - COMPRAS-8 (sonda P4-L11): proposta pública sem teto de total — 9.999.999.999 ×
 *    quantidade gravava 119.999.999.988 em `valor_respondido`.
 */

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Imap\MensagemEmail;
use App\Mail\RespostaCotacaoPorEmailRecebida;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

const MIGRATION_A4_FK = '2026_09_22_000001_fk_composta_cotacao_links_e_unidade_user_para_a_mae.php';

beforeEach(function () {
    Mail::fake();
    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'bravo-a4c', 'name' => 'Bravo', 'status' => 'active'])->id;
    Tenant::find($this->tenantB)->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    TenantContext::forget();
});

/** @return array{cotacao: Cotacao, link: CotacaoLink, url: string, itens: Collection, fornecedor: Fornecedor, compradora: User} */
function a4c_cenario(string $tenantId, array $estimados = [null, null]): array
{
    return TenantContext::runFor($tenantId, function () use ($tenantId, $estimados) {
        $compradora = User::factory()->create(['tenant_id' => $tenantId, 'email' => 'comp-'.uniqid().'@helix.test']);
        $unidade = Unidade::factory()->create();
        $requisicao = Requisicao::factory()->create([
            'unidade_id' => $unidade->id,
            'centro_custo_id' => CentroCusto::factory()->create(['unidade_id' => $unidade->id])->id,
            'solicitante_id' => $compradora->id,
            'status' => StatusRequisicao::EmCotacao,
            'codigo' => 'REQ-A4C-'.fake()->unique()->numerify('#####'),
        ]);
        $itens = collect([
            ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 2, 'valor_unitario_estimado' => $estimados[0]]),
            ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 10, 'valor_unitario_estimado' => $estimados[1]]),
        ]);
        $fornecedor = Fornecedor::factory()->homologado()->create(['contato_email' => 'forn-'.uniqid().'@alfa.test']);
        $cotacao = Cotacao::factory()->create([
            'requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'criada_por' => $compradora->id, 'valor' => null,
        ]);
        $emitido = app(CotacaoLinkService::class)->emitir($cotacao, now()->addDays(3));

        return ['cotacao' => $cotacao, 'link' => $emitido['link'], 'url' => $emitido['url'], 'itens' => $itens, 'fornecedor' => $fornecedor, 'compradora' => $compradora];
    });
}

function a4c_email(array $c, string $de, string $id, ?string $autenticacao = null): MensagemEmail
{
    return new MensagemEmail($id, "<{$id}@origem>", $de, 'RE: Cotação [COT-'.$c['link']->referencia.']', 'segue', autenticacao: $autenticacao);
}

// ─── COMPRAS-4 ───────────────────────────────────────────────────────────────

it('COMPRAS-4: remetente estranho não dispara avisos ilimitados — 1 por remetente, teto por cotação', function () {
    $c = a4c_cenario($this->tenantA);
    $acao = app(ProcessarRespostaCotacaoAction::class);

    // 25 mensagens do MESMO estranho, Message-ID distintos (o dedupe antigo era só por Message-ID).
    for ($i = 0; $i < 25; $i++) {
        $acao->execute(a4c_email($c, 'atacante@evil.test', "spam{$i}"));
    }
    expect(Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count())->toBe(1);

    // 25 remetentes DIFERENTES: o teto por cotação segura o resto.
    for ($i = 0; $i < 25; $i++) {
        $acao->execute(a4c_email($c, "bot{$i}@evil.test", "rot{$i}"));
    }
    expect(Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count())->toBe(config('compras.cotacao_email.avisos_de_estranhos_por_cotacao_dia'));

    // A trilha de auditoria também não é inundada: uma linha por aviso EMITIDO.
    expect(DB::table('audit_logs')->where('action', 'compras.cotacao_resposta_email_recebida')->count())
        ->toBe(config('compras.cotacao_email.avisos_de_estranhos_por_cotacao_dia'));
});

it('COMPRAS-4: o flood de estranhos não consome a cota do fornecedor verdadeiro (remetente confere + autenticado)', function () {
    $c = a4c_cenario($this->tenantA);
    $acao = app(ProcessarRespostaCotacaoAction::class);

    for ($i = 0; $i < 25; $i++) {
        $acao->execute(a4c_email($c, "bot{$i}@evil.test", "rot{$i}"));
    }
    $antes = Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count();

    // Carimbo do NOSSO MX (authserv-id), como em ProcessarRespostaCotacaoTest.
    config(['mail.imap.authserv_id' => 'mx.helix.test']);
    $de = $c['fornecedor']->contato_email;
    $dominio = substr((string) strrchr($de, '@'), 1);
    $aviso = $acao->execute(a4c_email($c, $de, 'legitimo-1', "mx.helix.test; spf=pass smtp.mailfrom={$de}; dkim=pass header.d={$dominio}; dmarc=pass header.from={$dominio}"));

    expect($aviso)->not->toBeNull()
        ->and(Mail::sent(RespostaCotacaoPorEmailRecebida::class)->count())->toBe($antes + 1);
});

it('COMPRAS-4: link revogado ou cotação fora de cotação não gera aviso', function () {
    $revogado = a4c_cenario($this->tenantA);
    DB::table('cotacao_links')->where('id', $revogado['link']->id)->update(['revogado_em' => now()]);

    $fechada = a4c_cenario($this->tenantA);
    DB::table('requisicoes')->where('id', $fechada['cotacao']->requisicao_id)->update(['status' => StatusRequisicao::Aprovada->value]);

    $acao = app(ProcessarRespostaCotacaoAction::class);
    expect($acao->execute(a4c_email($revogado, 'x@evil.test', 'r1')))->toBeNull()
        ->and($acao->execute(a4c_email($fechada, 'y@evil.test', 'f1')))->toBeNull();

    Mail::assertNothingSent();
});

it('COMPRAS-4: os limites vêm de config LITERAL', function () {
    $config = (require base_path('config/compras.php'))['cotacao_email'];

    expect($config['avisos_por_remetente_dia'])->toBe(1)
        ->and($config['avisos_de_estranhos_por_cotacao_dia'])->toBe(3)
        ->and($config['avisos_do_fornecedor_por_cotacao_dia'])->toBe(5);
});

// ─── COMPRAS-5 ───────────────────────────────────────────────────────────────

it('COMPRAS-5: o banco recusa cotacao_links de um tenant apontando para cotação de OUTRO', function () {
    $a = a4c_cenario($this->tenantA);

    expect(fn () => DB::table('cotacao_links')->insert([
        'tenant_id' => $this->tenantB, 'cotacao_id' => $a['cotacao']->id, 'fornecedor_id' => $a['cotacao']->fornecedor_id,
        'token_hash' => CotacaoLinkService::hash('tok-forjado-a4'), 'referencia' => (string) Str::ulid(),
        'expires_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('COMPRAS-5: o banco recusa cotacao_links com fornecedor de OUTRO tenant', function () {
    $a = a4c_cenario($this->tenantA);
    $fornecedorB = TenantContext::runFor($this->tenantB, fn () => Fornecedor::factory()->homologado()->create());

    expect(fn () => DB::table('cotacao_links')->insert([
        'tenant_id' => $this->tenantA, 'cotacao_id' => $a['cotacao']->id, 'fornecedor_id' => $fornecedorB->id,
        'token_hash' => CotacaoLinkService::hash('tok-forjado-a4-2'), 'referencia' => (string) Str::ulid(),
        'expires_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('COMPRAS-5: o banco recusa unidade_user com unidade de OUTRO tenant', function () {
    $usuario = TenantContext::runFor($this->tenantA, fn () => User::factory()->create());
    $unidadeB = TenantContext::runFor($this->tenantB, fn () => Unidade::factory()->create());

    expect(fn () => DB::table('unidade_user')->insert([
        'tenant_id' => $this->tenantA, 'user_id' => $usuario->id, 'unidade_id' => $unidadeB->id,
        'perfil' => Perfil::Aprovador->value, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('COMPRAS-5: a migration aborta com diagnóstico diante de linha cruzada, sem corrigir sozinha; é idempotente e reversível', function () {
    if (DB::getDriverName() !== 'sqlite') {
        // DDL no meio do teste faz commit implícito no MySQL e fura o RefreshDatabase.
        $this->markTestSkipped('Exercita up()/down() no meio do teste — SQLite-only. Em MySQL a migration roda de verdade no step "Run migrations" do CI.');
    }

    $migration = require database_path('migrations/'.MIGRATION_A4_FK);
    $compostas = fn () => collect(['cotacao_links', 'unidade_user'])
        ->flatMap(fn ($t) => collect(Schema::getForeignKeys($t))->filter(fn ($fk) => in_array($fk['foreign_table'], ['cotacoes', 'fornecedores', 'unidades'], true) && count($fk['columns']) === 2))
        ->count();

    expect($compostas())->toBe(3);
    $migration->up();
    expect($compostas())->toBe(3);

    $migration->down();
    expect($compostas())->toBe(0);
    $migration->down();

    // Estado legado: sem a FK, a linha cruzada entra.
    $a = a4c_cenario($this->tenantA);
    DB::table('cotacao_links')->insert([
        'tenant_id' => $this->tenantB, 'cotacao_id' => $a['cotacao']->id, 'fornecedor_id' => $a['cotacao']->fornecedor_id,
        'token_hash' => CotacaoLinkService::hash('tok-legado'), 'referencia' => (string) Str::ulid(),
        'expires_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'from cotacao_links cl join cotacoes m');
    expect($compostas())->toBe(0)
        ->and(DB::table('cotacao_links')->where('token_hash', CotacaoLinkService::hash('tok-legado'))->count())->toBe(1);

    DB::table('cotacao_links')->where('token_hash', CotacaoLinkService::hash('tok-legado'))->delete();
    $migration->up();
    expect($compostas())->toBe(3);
});

// ─── COMPRAS-8 ───────────────────────────────────────────────────────────────

it('COMPRAS-8: proposta pública com valor absurdo volta como erro de validação — nada grava e o link não é consumido', function () {
    $c = a4c_cenario($this->tenantA);

    $resposta = $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '9999999999', $c['itens'][1]->id => '9999999999']]);

    $resposta->assertStatus(302)->assertSessionHasErrors('precos.'.$c['itens'][0]->id);
    expect(DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBeNull()
        ->and(DB::table('cotacao_links')->where('id', $c['link']->id)->value('submetido_em'))->toBeNull();
});

it('COMPRAS-8: o TOTAL (unitário × quantidade) também tem teto, mesmo com cada unitário dentro do limite', function () {
    $c = a4c_cenario($this->tenantA);
    config()->set('compras.proposta_publica.valor_total_maximo', 1000.0);

    // 2 × 400 + 10 × 90 = 1.700 > 1.000
    $resposta = $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '400', $c['itens'][1]->id => '90']]);

    $resposta->assertStatus(302)->assertSessionHasErrors('precos');
    expect(DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBeNull()
        ->and(DB::table('cotacao_links')->where('id', $c['link']->id)->value('submetido_em'))->toBeNull();
});

it('COMPRAS-8: a proposta tem de ser coerente com a requisição — total muito acima do estimado é recusado', function () {
    // Estimado: 2 × 10 + 10 × 8 = 100 → teto de coerência = 100 × múltiplo (100) = 10.000.
    $c = a4c_cenario($this->tenantA, [10, 8]);

    $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '5000', $c['itens'][1]->id => '1']])
        ->assertStatus(302)->assertSessionHasErrors('precos');
    expect(DB::table('cotacao_links')->where('id', $c['link']->id)->value('submetido_em'))->toBeNull();

    // Dentro da coerência: grava normalmente.
    $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '12.50', $c['itens'][1]->id => '9']])->assertOk();
    expect((float) DB::table('cotacoes')->where('id', $c['cotacao']->id)->value('valor_respondido'))->toBe(115.0);
});

it('COMPRAS-8: validade da proposta tem janela máxima', function () {
    $c = a4c_cenario($this->tenantA);

    $this->post($c['url'], ['precos' => [$c['itens'][0]->id => '10'], 'validade_proposta' => now()->addYears(30)->toDateString()])
        ->assertStatus(302)->assertSessionHasErrors('validade_proposta');
    expect(DB::table('cotacao_links')->where('id', $c['link']->id)->value('submetido_em'))->toBeNull();
});
