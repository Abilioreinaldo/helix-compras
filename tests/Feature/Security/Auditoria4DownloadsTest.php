<?php

/**
 * 4ª auditoria adversarial — COMPRAS-2 (MÉDIO).
 *
 * A sonda P4-T3b provou: `arquivo=500 pdf=200 | novas linhas em audit_logs: 0`.
 *  - o download do anexo de cotação quebrava SEMPRE (TypeError no tipo de retorno) e
 *    nenhum teste cobria a rota;
 *  - PDF do pedido (fornecedor, preços, aprovadores), anexo de cotação e o CSV de
 *    agendamentos saíam sem linha na trilha de auditoria.
 */

use App\Livewire\Financeiro\Agendamentos;
use App\Models\Cotacao;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function a4_trilha(string $acao): Collection
{
    return DB::table('audit_logs')->where('action', $acao)->get();
}

it('COMPRAS-2: o anexo de cotação baixa (200, não 500) e deixa trilha de auditoria', function () {
    $compradora = User::factory()->compradora()->create();
    $cotacao = Cotacao::factory()->create(['arquivo_path' => 'cotacoes/a.pdf', 'arquivo_nome_original' => 'proposta.pdf']);
    Storage::disk('local')->put('cotacoes/a.pdf', 'conteudo-da-proposta');

    $resposta = $this->actingAs($compradora)->get('/compradora/cotacoes/arquivo/'.$cotacao->id);

    $resposta->assertOk();
    expect($resposta->headers->get('content-disposition'))->toContain('proposta.pdf')
        ->and($resposta->streamedContent())->toBe('conteudo-da-proposta');

    $trilha = a4_trilha('compras.cotacao_arquivo_baixado');
    expect($trilha)->toHaveCount(1)
        ->and((string) $trilha[0]->actor_id)->toBe((string) $compradora->id)
        ->and($trilha[0]->tenant_id)->toBe(TenantContext::id())
        ->and($trilha[0]->resource_type)->toBe(Cotacao::class)
        ->and((string) $trilha[0]->resource_id)->toBe((string) $cotacao->id)
        ->and($trilha[0]->metadata)->toContain('proposta.pdf');
});

it('COMPRAS-2: o PDF do pedido de compra deixa trilha de auditoria', function () {
    $compradora = User::factory()->compradora()->create();
    $pedido = PedidoCompra::factory()->emitido()->create();

    $this->actingAs($compradora)->get('/compradora/pedidos/'.$pedido->id.'/pdf')->assertOk();

    $trilha = a4_trilha('compras.pedido_pdf_baixado');
    expect($trilha)->toHaveCount(1)
        ->and((string) $trilha[0]->actor_id)->toBe((string) $compradora->id)
        ->and($trilha[0]->resource_type)->toBe(PedidoCompra::class)
        ->and((string) $trilha[0]->resource_id)->toBe((string) $pedido->id);
});

it('COMPRAS-2: download NEGADO não grava trilha de "baixado" (só o que saiu é auditado como saída)', function () {
    $cotacaoA = Cotacao::factory()->create(['arquivo_path' => 'cotacoes/a.pdf', 'arquivo_nome_original' => 'a.pdf']);
    Storage::disk('local')->put('cotacoes/a.pdf', 'segredo-A');
    $pedidoA = PedidoCompra::factory()->emitido()->create();

    $tenantB = Tenant::create(['slug' => 'bravo-a4d', 'name' => 'Bravo', 'status' => 'active'])->id;
    Tenant::find($tenantB)->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    $compradoraB = TenantContext::runFor($tenantB, fn () => User::factory()->compradora()->create(['tenant_id' => $tenantB, 'email' => 'cb@a4d.test']));

    TenantContext::set($tenantB);
    expect($this->actingAs($compradoraB)->get('/compradora/cotacoes/arquivo/'.$cotacaoA->id)->status())->toBeIn([403, 404])
        ->and($this->actingAs($compradoraB)->get('/compradora/pedidos/'.$pedidoA->id.'/pdf')->status())->toBeIn([403, 404]);

    expect(DB::table('audit_logs')->whereIn('action', ['compras.cotacao_arquivo_baixado', 'compras.pedido_pdf_baixado'])->count())->toBe(0);
});

it('COMPRAS-2 (irmão): o CSV de agendamentos (lista para o banco) deixa trilha de auditoria', function () {
    $financeiro = User::factory()->financeiro()->create();
    Pagamento::factory()->count(2)->create(['data_vencimento' => now()->addDays(5)->toDateString()]);

    Livewire::actingAs($financeiro)->test(Agendamentos::class)->call('exportar');

    $trilha = a4_trilha('compras.agendamentos_exportados');
    expect($trilha)->toHaveCount(1)
        ->and((string) $trilha[0]->actor_id)->toBe((string) $financeiro->id)
        ->and($trilha[0]->tenant_id)->toBe(TenantContext::id())
        // decodifica em vez de comparar texto: o MySQL normaliza a coluna JSON com espaços.
        ->and(json_decode((string) $trilha[0]->metadata, true)['linhas'] ?? null)->toBe(2);
});
