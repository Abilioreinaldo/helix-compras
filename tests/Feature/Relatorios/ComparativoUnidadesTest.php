<?php

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Livewire\Relatorios\ComparativoUnidades;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Emite um PC com gasto $valor. A requisição pertence a $unidadeReq;
 * o próprio pedido é registrado em $unidadePc (que pode divergir — é o ponto do R5).
 */
function r5_emitir(User $compradora, Unidade $unidadeReq, Unidade $unidadePc, Fornecedor $fornecedor, float $valor): void
{
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidadeReq->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidadeReq->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidadeReq->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => 'Item R5',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valor / 5.0,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => $valor,
        'vencedora' => true,
        'criada_por' => $compradora->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    $seq = fake()->unique()->numberBetween(1, 9999);
    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidadePc->id,
        'criado_por' => $compradora->id,
        'numero' => sprintf('PC-2026-%04d', $seq),
        'ano' => 2026,
        'sequencia' => $seq,
        'emitido_em' => now(),
        'emitido_por' => $compradora->id,
    ]);

    $pedido->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Item R5',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario' => $valor / 5.0,
        'valor_total' => $valor,
        'destino' => 'Depósito Central',
    ]);
}

// ─── Autorização ───────────────────────────────────────────────────────────────

it('rota_comparativo_unidades_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.comparativo-unidades'))
        ->assertForbidden();
});

it('rota_comparativo_unidades_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.comparativo-unidades'))
        ->assertSuccessful();
});

// ─── Estado vazio ────────────────────────────────────────────────────────────

it('comparativo_unidades_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.comparativo-unidades'))
        ->assertSuccessful()
        ->assertSee('Nenhum gasto registrado');
});

// ─── Agregação ─────────────────────────────────────────────────────────────────

it('comparativo_unidades_agrega_gasto_por_unidade', function () {
    $compradora = User::factory()->compradora()->create();
    $unidade = Unidade::factory()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    r5_emitir($compradora, $unidade, $unidade, $fornecedor, 500.0);

    $this->actingAs($compradora)
        ->get(route('relatorios.comparativo-unidades'))
        ->assertSuccessful()
        ->assertSee($unidade->nome)
        ->assertSee('500,00');
});

// ─── Adversário: gasto vai para a unidade da REQUISIÇÃO, não a do PEDIDO ──────────

it('comparativo_unidades_atribui_gasto_a_unidade_da_requisicao', function () {
    $compradora = User::factory()->compradora()->create();
    $unidadeReq = Unidade::factory()->create(['nome' => 'Unidade da Requisicao']);
    $unidadePc = Unidade::factory()->create(['nome' => 'Unidade do Pedido']);
    $fornecedor = Fornecedor::factory()->homologado()->create();

    // Requisição na unidadeReq, mas o pedido emitido na unidadePc.
    r5_emitir($compradora, $unidadeReq, $unidadePc, $fornecedor, 800.0);

    $resultados = Livewire::actingAs($compradora)
        ->test(ComparativoUnidades::class)
        ->viewData('resultados');

    expect($resultados)->toHaveCount(1);
    expect((int) $resultados->first()->unidade_id)->toBe($unidadeReq->id);
    expect((float) $resultados->first()->total_gasto)->toBe(800.0);
});
