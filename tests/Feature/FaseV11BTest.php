<?php

use App\Actions\FusaoSaldosAction;
use App\Actions\SugerirVinculoCatalogoAction;
use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\SaldoFusaoLog;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function v11b_criarSaldo(
    Unidade $unidade,
    string $descricao,
    string $deposito = 'Depósito Central',
    float $quantidade = 10.0,
    float $cmp = 50.0,
    ?int $itemCatalogoId = null
): SaldoEstoque {
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => $descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($descricao),
        'unidade_medida' => 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $quantidade * $cmp,
        'item_catalogo_id' => $itemCatalogoId,
    ]);
}

// ─── PASSO 0a — SugerirVinculoCatalogoAction: saldo já vinculado retorna vazio ─

it('sugestao_retorna_colecao_vazia_para_saldo_ja_vinculado', function () {
    $unidade = Unidade::factory()->create();
    $catalogoItem = CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado 1/4']);

    // Saldo com vínculo de catálogo existente
    $saldo = v11b_criarSaldo($unidade, 'Parafuso Sextavado 1/4', itemCatalogoId: $catalogoItem->id);

    // Mesmo com descrição idêntica a um item de catálogo ativo, deve retornar vazio
    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->toBeEmpty();
});

it('sugestao_ainda_funciona_para_saldo_sem_vinculo', function () {
    $unidade = Unidade::factory()->create();
    CatalogoItem::factory()->create(['descricao' => 'Parafuso Sextavado 1/4']);

    $saldo = v11b_criarSaldo($unidade, 'Parafuso Sextavado 1/4');

    $sugestoes = app(SugerirVinculoCatalogoAction::class)->execute($saldo);

    expect($sugestoes)->not->toBeEmpty()
        ->and($sugestoes->first()['confianca'])->toBe('alta');
});

// ─── PASSO 0b — FormularioRequisicao: busca server-side do catálogo ────────────

it('formulario_requisicao_busca_server_side_filtra_itens_catalogo', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    // Cria 60 itens (acima do limite de 50) para validar que não carrega tudo
    CatalogoItem::factory()->count(55)->create(['descricao' => fn () => 'Item Generico '.fake()->unique()->numerify('###')]);
    $itemEspecial = CatalogoItem::factory()->create(['descricao' => 'Luva de Raspa de Couro Especial']);

    $component = Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->set('buscaCatalogo', 'Luva de Raspa');

    // Deve exibir apenas o item que bate com a busca
    $itensCatalogo = $component->viewData('itensCatalogo');
    expect($itensCatalogo)->toHaveCount(1)
        ->and($itensCatalogo->first()->id)->toBe($itemEspecial->id);
});

it('formulario_requisicao_sem_busca_limita_a_50_itens', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    CatalogoItem::factory()->count(60)->create();

    $component = Livewire::actingAs($solicitante)->test(FormularioRequisicao::class);

    $itensCatalogo = $component->viewData('itensCatalogo');
    expect(count($itensCatalogo))->toBeLessThanOrEqual(50);
});

it('formulario_valida_item_catalogo_mesmo_fora_da_pagina_corrente', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    // Cria 55 itens que viriam primeiro na ordenação alfabética (prefixo "Aaaa")
    CatalogoItem::factory()->count(55)->sequence(
        fn ($seq) => ['descricao' => 'Aaaa Item '.str_pad((string) $seq->index, 3, '0', STR_PAD_LEFT)]
    )->create();

    // Item que estaria fora do limit(50) quando ordenado alfabeticamente
    $itemForaDaPagina = CatalogoItem::factory()->create(['descricao' => 'Zzzz Item Especial Fora da Pagina']);

    // A validação Rule::exists vai direto ao banco — deve passar mesmo fora do limit
    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->set('unidadeId', $unidade->id)
        ->set('centroCustoId', $centro->id)
        ->call('selecionarItemCatalogo', 0, $itemForaDaPagina->id)
        ->set('itens.0.quantidade', '1')
        ->call('salvar')
        ->assertHasNoErrors(['itens.0.item_catalogo_id']);
});

// ─── PASSO 1 — FusaoSaldosAction ─────────────────────────────────────────────

it('fusao_de_dois_saldos_soma_quantidade_e_calcula_cmp_ponderado', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    // 10un CMP5 (valor 50) + 30un CMP9 (valor 270) = 40un CMP8 valor 320
    $saldo1 = v11b_criarSaldo($unidade, 'Item A', quantidade: 10.0, cmp: 5.0, itemCatalogoId: $catalogo->id);
    $saldo2 = v11b_criarSaldo($unidade, 'Item A', deposito: 'Deposito B', quantidade: 30.0, cmp: 9.0, itemCatalogoId: $catalogo->id);

    // Para fundir, devem estar no mesmo deposito — usamos mesma unidade/deposito/catalogo
    $saldoA = v11b_criarSaldo($unidade, 'Item Fusao A', 'Almox Principal', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item Fusao B', 'Almox Principal', 30.0, 9.0, $catalogo->id);

    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    expect((float) $destino->quantidade)->toEqualWithDelta(40.0, 0.001)
        ->and((float) $destino->custo_medio_ponderado)->toEqualWithDelta(8.0, 0.0001)
        ->and((float) $destino->valor_total)->toEqualWithDelta(320.0, 0.01);
});

it('fusao_preserva_ledger_sem_alterar_movimentacoes_existentes', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    // Cria movimentacoes pre-existentes
    MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldoA->id,
        'tipo' => TipoMovimentacao::Entrada,
        'quantidade' => 10.0,
        'custo_unitario' => 5.0,
        'valor_total' => 50.0,
        'registrado_por' => $admin->id,
    ]);
    MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldoB->id,
        'tipo' => TipoMovimentacao::Entrada,
        'quantidade' => 30.0,
        'custo_unitario' => 9.0,
        'valor_total' => 270.0,
        'registrado_por' => $admin->id,
    ]);

    $idsAntes = MovimentacaoEstoque::pluck('id')->sort()->values()->toArray();

    app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    // Movimentacoes pre-existentes preservadas (ids originais ainda existem)
    $idsDepois = MovimentacaoEstoque::pluck('id')->sort()->values()->toArray();
    foreach ($idsAntes as $id) {
        expect(in_array($id, $idsDepois))->toBeTrue("Movimentacao #{$id} foi removida do ledger.");
    }

    // Novas movimentacoes de fusao foram criadas (ledger grew)
    expect(count($idsDepois))->toBeGreaterThan(count($idsAntes));
});

it('fusao_origem_vira_tombstone_com_quantidade_zero', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    $saldoB->refresh();
    expect((float) $saldoB->quantidade)->toBe(0.0)
        ->and($saldoB->fundido_para_id)->toBe($destino->id)
        ->and($saldoB->fundido_em)->not->toBeNull();

    // Destino nao e tombstone
    $destino->refresh();
    expect($destino->fundido_para_id)->toBeNull();
});

it('fusao_idempotencia_origens_ja_fundidas_nao_geram_dupla_contagem', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);
    $qtdApos1Fusao = (float) $destino->quantidade;

    // Tentar fundir de novo com origens ja tombstone deve lancar ValidationException
    expect(fn () => app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin))
        ->toThrow(ValidationException::class);

    $destino->refresh();
    expect((float) $destino->quantidade)->toEqualWithDelta($qtdApos1Fusao, 0.001);
});

it('fusao_rejeita_saldos_de_unidades_diferentes', function () {
    $admin = User::factory()->admin()->create();
    $unidadeA = Unidade::factory()->create();
    $unidadeB = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidadeA, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidadeB, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    expect(fn () => app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin))
        ->toThrow(ValidationException::class);
});

it('fusao_cria_snapshot_no_saldo_fusao_log', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $qtdOrigemB = (float) $saldoB->quantidade;
    $cmpOrigemB = (float) $saldoB->custo_medio_ponderado;
    $valorOrigemB = (float) $saldoB->valor_total;

    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    // Deve haver 1 linha por origem (saldoB e a origem; saldoA e o destino)
    expect(SaldoFusaoLog::count())->toBe(1);

    $log = SaldoFusaoLog::first();
    expect($log->saldo_destino_id)->toBe($destino->id)
        ->and($log->saldo_origem_id)->toBe($saldoB->id)
        ->and((float) $log->quantidade_origem)->toEqualWithDelta($qtdOrigemB, 0.001)
        ->and((float) $log->cmp_origem)->toEqualWithDelta($cmpOrigemB, 0.0001)
        ->and((float) $log->valor_total_origem)->toEqualWithDelta($valorOrigemB, 0.01);
});

it('fusao_rejeita_nao_admin', function () {
    $naoAdmin = User::factory()->create(['is_admin' => false]);
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    expect(fn () => app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $naoAdmin))
        ->toThrow(HttpException::class);
});
