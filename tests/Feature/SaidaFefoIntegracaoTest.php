<?php

use App\Actions\AtenderRequisicaoMaterialAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Enums\StatusRequisicaoMaterial;
use App\Enums\TipoMovimentacao;
use App\Livewire\Compradora\TriagemRequisicoes;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\ItemRequisicao;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\Requisicao;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Saldo controla_lote com os lotes informados (já satisfazendo SUM(lotes)==saldo).
 *
 * @param  array<int, array{numero: string, validade: ?string, qtd: float}>  $lotes
 */
function sfi_saldoComLotes(Unidade $unidade, array $lotes, float $cmp = 50.0): SaldoEstoque
{
    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Insumo FEFO Integração', 'controla_lote' => true]);
    $total = array_sum(array_map(fn ($l) => $l['qtd'], $lotes));

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => $total,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $total * $cmp,
        'item_catalogo_id' => $catalogo->id,
    ]);

    foreach ($lotes as $l) {
        LoteEstoque::factory()->create([
            'saldo_estoque_id' => $saldo->id,
            'numero_lote' => $l['numero'],
            'validade' => $l['validade'],
            'quantidade' => $l['qtd'],
            'fundido_para_id' => null,
        ]);
    }

    return $saldo;
}

// ─── RIM multi-lote: rastreabilidade completa (P0 da revisão) ─────────────────

it('rim_multilote_vincula_requisicao_material_id_em_todas_as_movimentacoes', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $saldo = sfi_saldoComLotes($unidade, [
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 6.0],
    ]);

    // Solicita 6 → cruza dois lotes (4 de L-A + 2 de L-B) = 2 movimentações.
    $rim = RequisicaoMaterial::create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'saldo_estoque_id' => $saldo->id,
        'quantidade_solicitada' => 6.0,
        'justificativa' => 'Uso imediato multi-lote',
        'status' => StatusRequisicaoMaterial::Aberta,
    ]);

    app(AtenderRequisicaoMaterialAction::class)->execute($rim, $almoxarife);

    $saidas = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->get();

    expect($saidas)->toHaveCount(2)                                          // 1 por lote
        ->and($saidas->whereNull('requisicao_material_id'))->toHaveCount(0)  // NENHUMA órfã
        ->and($saidas->where('requisicao_material_id', $rim->id))->toHaveCount(2)
        ->and($saidas->whereNull('lote_estoque_id'))->toHaveCount(0);        // todas com lote
});

// ─── Atendimento direto (Triagem) sobre item controla_lote: FEFO de verdade ───

it('atendimento_direto_via_triagem_baixa_por_fefo_multi_lote', function () {
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::CompradoraSenior->value]);
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $saldo = sfi_saldoComLotes($unidade, [
        ['numero' => 'L-A', 'validade' => '2027-01-01', 'qtd' => 4.0],
        ['numero' => 'L-B', 'validade' => '2027-06-01', 'qtd' => 6.0],
    ]);
    $catalogoId = $saldo->item_catalogo_id;

    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoTriagem,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-SFI-'.fake()->unique()->numerify('#####'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);
    ItemRequisicao::create([
        'requisicao_id' => $requisicao->id,
        'descricao' => $saldo->descricao_item,
        'quantidade' => 6.0,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => 50.0,
        'item_catalogo_id' => $catalogoId,
        'avulso' => false,
    ]);

    Livewire::actingAs($compradora)
        ->test(TriagemRequisicoes::class)
        ->call('atenderDoEstoque', $requisicao->id)
        ->assertHasNoErrors();

    $saidas = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Saida)->get();

    expect($saidas)->toHaveCount(2)                                     // FEFO cruzou os 2 lotes
        ->and($saidas->whereNull('lote_estoque_id'))->toHaveCount(0)    // todas por lote
        ->and($saidas->whereNotNull('requisicao_material_id'))->toHaveCount(0)  // não é RIM
        ->and((float) $saldo->refresh()->quantidade)->toBe(4.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe(4.0);
});
