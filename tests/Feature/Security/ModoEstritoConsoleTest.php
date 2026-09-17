<?php

use App\Actions\ProcessarRespostaCotacaoAction;
use App\Enums\StatusRequisicao;
use App\Imap\MensagemEmail;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\PrecoHomologado;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use App\Models\Scopes\UnidadeScope;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Modo estrito (HELIX_TENANCY_STRICT) no console.
|--------------------------------------------------------------------------
|
| O Pest.php fixa o tenant canônico no contexto — o que esconderia um comando que
| esquece o runFor. Aqui o contexto é ZERADO antes de rodar (como no scheduler/fila
| de produção): cada comando/captura estabelece o tenant sozinho (ForEachTenant /
| runFor), processa TODOS os tenants e não lança MissingTenantContextException.
|
*/

beforeEach(function () {
    expect(config('foundation.tenancy.strict'))->toBeTrue();

    $this->tenants = [
        TenantContext::id(),
        Tenant::create(['slug' => 'estrito-b', 'name' => 'Estrito B', 'status' => 'active'])->id,
    ];
});

it('requisicoes:marcar-atrasadas percorre todos os tenants sem contexto e carimba o log', function () {
    $requisicoes = array_map(fn (string $tenant) => TenantContext::runFor($tenant, fn () => Requisicao::factory()
        ->aguardandoTriagem()
        ->create(['submetida_em' => now()->subHours(30)])), $this->tenants);

    TenantContext::forget();

    $this->artisan('requisicoes:marcar-atrasadas')->assertSuccessful();

    foreach ($requisicoes as $requisicao) {
        $atual = Requisicao::withoutTenantScope()->withoutGlobalScope(UnidadeScope::class)->find($requisicao->id);

        expect($atual->atrasada)->toBeTrue()
            ->and(RequisicaoLog::withoutTenantScope()->where('requisicao_id', $requisicao->id)->value('tenant_id'))
            ->toBe($requisicao->tenant_id);
    }
});

it('precos:expirar-homologacoes percorre todos os tenants sem contexto', function () {
    $precos = array_map(fn (string $tenant) => TenantContext::runFor($tenant, fn () => PrecoHomologado::factory()->vencido()->create()), $this->tenants);

    TenantContext::forget();

    $this->artisan('precos:expirar-homologacoes')->assertSuccessful();

    foreach ($precos as $preco) {
        expect(PrecoHomologado::withoutTenantScope()->find($preco->id)->ativo)->toBeFalse();
    }
});

it('aprovacoes:lembrar-pendentes roda sem contexto', function () {
    Mail::fake();

    foreach ($this->tenants as $tenant) {
        TenantContext::runFor($tenant, fn () => Requisicao::factory()->create([
            'status' => StatusRequisicao::AguardandoAprovacao,
            'aprovacao_iniciada_em' => now()->subDays(3),
        ]));
    }

    TenantContext::forget();

    $this->artisan('aprovacoes:lembrar-pendentes')->assertSuccessful();
});

it('captura IMAP resolve o tenant pela referência do link (sem contexto) e só avisa', function () {
    Mail::fake();
    config(['mail.imap.authserv_id' => 'mx.helix.test']);

    [$cotacao, $referencia] = TenantContext::runFor($this->tenants[1], function () {
        $cotacao = Cotacao::factory()->create([
            // 4ª auditoria (COMPRAS-4): só cotação com requisição EM COTAÇÃO recebe aviso.
            'requisicao_id' => Requisicao::factory()->create(['status' => StatusRequisicao::EmCotacao])->id,
            'fornecedor_id' => Fornecedor::factory()->create(['contato_email' => 'fornecedor@estrito.test'])->id,
            'valor' => null,
        ]);

        return [$cotacao, app(CotacaoLinkService::class)->emitir($cotacao, now()->addDays(2))['link']->referencia];
    });

    TenantContext::forget();

    $resultado = app(ProcessarRespostaCotacaoAction::class)->execute(new MensagemEmail(
        id: 'uid-estrito',
        messageId: '<estrito@fornecedor>',
        de: 'fornecedor@estrito.test',
        assunto: "Re: Solicitação de cotação [COT-{$referencia}]",
        corpo: 'Valor: R$ 150,00 | Prazo: 15 dias',
        autenticacao: 'mx.helix.test; spf=pass smtp.mailfrom=fornecedor@estrito.test; dkim=pass header.d=estrito.test; dmarc=pass header.from=estrito.test',
    ));

    expect($resultado?->id)->toBe($cotacao->id)
        ->and(Cotacao::withoutTenantScope()->find($cotacao->id)->resposta_recebida_em)->toBeNull();
});
