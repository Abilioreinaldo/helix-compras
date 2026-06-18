<?php

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Livewire\Relatorios\GastosFornecedor;
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

function r7_setup(string $categoria = 'materiais'): array
{
    $compradora = User::factory()->compradora()->create();
    $unidade = Unidade::factory()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create(['categoria' => $categoria]);
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    return compact('compradora', 'unidade', 'fornecedor', 'solicitante', 'centro');
}

function r7_emitir_pc(array $setup, float $valorTotal = 500.0): PedidoCompra
{
    $requisicao = Requisicao::create([
        'solicitante_id' => $setup['solicitante']->id,
        'unidade_id' => $setup['unidade']->id,
        'centro_custo_id' => $setup['centro']->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    $seq = fake()->unique()->numberBetween(1, 9999);

    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => 'Item R7',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorTotal / 5.0,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id,
        'fornecedor_id' => $setup['fornecedor']->id,
        'valor' => $valorTotal,
        'vencedora' => true,
        'criada_por' => $setup['compradora']->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'fornecedor_id' => $setup['fornecedor']->id,
        'unidade_id' => $setup['unidade']->id,
        'criado_por' => $setup['compradora']->id,
        'numero' => sprintf('PC-2026-%04d', $seq),
        'ano' => 2026,
        'sequencia' => $seq,
        'emitido_em' => now(),
        'emitido_por' => $setup['compradora']->id,
    ]);

    $pedido->itens()->create([
        'requisicao_id' => $requisicao->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Item R7',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario' => $valorTotal / 5.0,
        'valor_total' => $valorTotal,
        'destino' => 'Depósito Central',
    ]);

    return $pedido;
}

// ─── Autorização ───────────────────────────────────────────────────────────────

it('rota_gastos_fornecedor_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.gastos-fornecedor'))
        ->assertForbidden();
});

it('rota_gastos_fornecedor_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.gastos-fornecedor'))
        ->assertSuccessful();
});

// ─── Estado vazio ────────────────────────────────────────────────────────────

it('gastos_fornecedor_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.gastos-fornecedor'))
        ->assertSuccessful()
        ->assertSee('Nenhum gasto encontrado');
});

// ─── Agregação ─────────────────────────────────────────────────────────────────

it('gastos_fornecedor_agrega_valor_e_exibe_categoria_do_fornecedor', function () {
    $setup = r7_setup('materiais');
    r7_emitir_pc($setup, 500.0);

    $this->actingAs($setup['compradora'])
        ->get(route('relatorios.gastos-fornecedor'))
        ->assertSuccessful()
        ->assertSee($setup['fornecedor']->nome_fantasia)
        ->assertSee('materiais')
        ->assertSee('500,00');
});

it('gastos_fornecedor_agrupa_por_categoria_do_fornecedor', function () {
    $setup = r7_setup('equipamentos');
    r7_emitir_pc($setup, 300.0);

    Livewire::actingAs($setup['compradora'])
        ->test(GastosFornecedor::class)
        ->set('agrupamento', 'categoria')
        ->assertSee('equipamentos')
        ->assertSee('300,00');
});

it('gastos_fornecedor_ignora_pedido_nao_emitido', function () {
    $setup = r7_setup('materiais');
    $pedido = r7_emitir_pc($setup, 500.0);
    $pedido->update(['status' => StatusPedidoCompra::Rascunho, 'emitido_em' => null]);

    $this->actingAs($setup['compradora'])
        ->get(route('relatorios.gastos-fornecedor'))
        ->assertSuccessful()
        ->assertSee('Nenhum gasto encontrado');
});
