<?php

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\Obra;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ─────────────────────────────────────────────────────────────────

function f8_setup(): array
{
    $compradora = User::factory()->compradora()->create();
    $unidade = Unidade::factory()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    return compact('compradora', 'unidade', 'fornecedor', 'solicitante', 'centro');
}

function f8_requisicao(array $setup, array $extra = []): Requisicao
{
    return Requisicao::create(array_merge([
        'solicitante_id' => $setup['solicitante']->id,
        'unidade_id' => $setup['unidade']->id,
        'centro_custo_id' => $setup['centro']->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ], $extra));
}

function f8_emitir_pc(array $setup, Requisicao $requisicao, float $valorTotal = 500.0): PedidoCompra
{
    $seq = fake()->unique()->numberBetween(1, 9999);

    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => 'Item F8',
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
        'descricao' => 'Item F8',
        'quantidade' => 5.0,
        'unidade_medida' => 'un',
        'valor_unitario' => $valorTotal / 5.0,
        'valor_total' => $valorTotal,
        'destino' => 'Depósito Central',
    ]);

    return $pedido;
}

// ─── Autorização — 403 / 200 ─────────────────────────────────────────────────

it('rota_gastos_cc_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.gastos-cc'))
        ->assertForbidden();
});

it('rota_gastos_cc_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.gastos-cc'))
        ->assertSuccessful();
});

it('rota_pendentes_aprovador_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.pendentes-aprovador'))
        ->assertForbidden();
});

it('rota_pendentes_aprovador_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.pendentes-aprovador'))
        ->assertSuccessful();
});

it('rota_custo_obra_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.custo-obra'))
        ->assertForbidden();
});

it('rota_custo_obra_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.custo-obra'))
        ->assertSuccessful();
});

it('rota_emergenciais_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.emergenciais'))
        ->assertForbidden();
});

it('rota_emergenciais_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.emergenciais'))
        ->assertSuccessful();
});

// ─── Estado Vazio ─────────────────────────────────────────────────────────────

it('gastos_cc_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.gastos-cc'))
        ->assertSuccessful()
        ->assertSee('Nenhum gasto encontrado');
});

it('pendentes_aprovador_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.pendentes-aprovador'))
        ->assertSuccessful()
        ->assertSee('Nenhuma aprovação pendente');
});

it('custo_obra_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.custo-obra'))
        ->assertSuccessful()
        ->assertSee('Nenhum gasto vinculado a obras');
});

it('emergenciais_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.emergenciais'))
        ->assertSuccessful()
        ->assertSee('Nenhuma compra emergencial');
});

// ─── Agregação Correta ────────────────────────────────────────────────────────

it('gastos_cc_agrega_valor_por_centro_de_custo', function () {
    $setup = f8_setup();
    $req = f8_requisicao($setup);
    f8_emitir_pc($setup, $req, 500.0);

    $this->actingAs($setup['compradora'])
        ->get(route('relatorios.gastos-cc'))
        ->assertSuccessful()
        ->assertSee($setup['centro']->nome)
        ->assertSee('500,00');
});

it('pendentes_aprovador_exibe_aprovador_com_requisicao_pendente', function () {
    $setup = f8_setup();
    $aprovador = User::factory()->create();

    $req = f8_requisicao($setup, [
        'status' => StatusRequisicao::AguardandoAprovacao,
        'ciclo_aprovacao' => 1,
    ]);

    Aprovacao::create([
        'requisicao_id' => $req->id,
        'etapa_alcada_id' => null,
        'ciclo' => 1,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor,
        'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente,
        'aprovador_id' => $aprovador->id,
        'justificativa' => null,
        'decidida_em' => null,
    ]);

    $this->actingAs($setup['compradora'])
        ->get(route('relatorios.pendentes-aprovador'))
        ->assertSuccessful()
        ->assertSee($aprovador->name);
});

it('custo_obra_exibe_custo_acumulado_por_mes', function () {
    $compradora = User::factory()->compradora()->create();
    $obraUnidade = Unidade::factory()->obra()->create();
    $obra = Obra::factory()->create(['unidade_id' => $obraUnidade->id, 'verba' => 10000.0]);

    $fornecedor = Fornecedor::factory()->homologado()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($obraUnidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $obraUnidade->id]);

    $req = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $obraUnidade->id,
        'centro_custo_id' => $centro->id,
        'obra_id' => $obra->id,
        'status' => StatusRequisicao::Aprovada,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);

    $seq = fake()->unique()->numberBetween(1, 9999);
    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $req->id,
        'descricao' => 'Material de Obra',
        'quantidade' => 10.0,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => 300.0,
    ]);
    $cotacao = Cotacao::create([
        'requisicao_id' => $req->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => 3000.0,
        'vencedora' => true,
        'criada_por' => $compradora->id,
        'vencedora_definida_em' => now()->subMinutes(30),
    ]);
    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Emitido,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $obraUnidade->id,
        'criado_por' => $compradora->id,
        'numero' => sprintf('PC-2026-%04d', $seq),
        'ano' => 2026,
        'sequencia' => $seq,
        'emitido_em' => now(),
        'emitido_por' => $compradora->id,
    ]);
    $pedido->itens()->create([
        'requisicao_id' => $req->id,
        'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id,
        'descricao' => 'Material de Obra',
        'quantidade' => 10.0,
        'unidade_medida' => 'un',
        'valor_unitario' => 300.0,
        'valor_total' => 3000.0,
        'destino' => 'Canteiro',
    ]);

    $this->actingAs($compradora)
        ->get(route('relatorios.custo-obra'))
        ->assertSuccessful()
        ->assertSee($obraUnidade->nome);
});

it('emergenciais_usa_cotacao_vencedora_como_cascata_de_valor', function () {
    $setup = f8_setup();

    $req = f8_requisicao($setup, [
        'is_emergencial' => true,
        'status' => StatusRequisicao::Aprovada,
        'submetida_em' => now(),
    ]);

    Cotacao::create([
        'requisicao_id' => $req->id,
        'fornecedor_id' => $setup['fornecedor']->id,
        'valor' => 500.0,
        'vencedora' => true,
        'criada_por' => $setup['compradora']->id,
        'vencedora_definida_em' => now()->subMinutes(10),
    ]);

    $this->actingAs($setup['compradora'])
        ->get(route('relatorios.emergenciais'))
        ->assertSuccessful()
        ->assertSee($setup['solicitante']->name)
        ->assertSee('500,00');
});
