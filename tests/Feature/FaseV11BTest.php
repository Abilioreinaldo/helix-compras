<?php

use App\Actions\ConfirmarVinculoSaldoAction;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

/**
 * Modela o estado legado PRÉ-Passo-3: as duplicatas de saldo (mesma unidade/depósito/
 * catálogo) que a fusão e o saneamento resolvem só existem ANTES do UNIQUE de catálogo.
 * Em produção o saneamento roda antes da migration do UNIQUE; aqui, como todas as
 * migrations sobem no boot, removemos o índice no Arrange. O RefreshDatabase reverte o
 * drop ao fim de cada teste. A constraint em si é coberta pelos testes do Passo 3.
 */
function v11b_semConstraintCatalogo(): void
{
    DB::statement('DROP INDEX IF EXISTS saldos_estoque_catalogo_unique');
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
//
// Estes testes fundem duplicatas do mesmo trio (unidade/depósito/catálogo). Essas
// duplicatas só existem no estado legado, ANTES do UNIQUE do Passo 3 — por isso o
// Arrange chama v11b_semConstraintCatalogo() (a constraint é coberta pelos testes do
// Passo 3, mais abaixo).

it('fusao_de_dois_saldos_soma_quantidade_e_calcula_cmp_ponderado', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    // 10un CMP5 (valor 50) + 30un CMP9 (valor 270) = 40un CMP8 valor 320
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
    v11b_semConstraintCatalogo();

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
    v11b_semConstraintCatalogo();

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
    v11b_semConstraintCatalogo();

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
    // Unidades diferentes ⇒ trios diferentes ⇒ a constraint do Passo 3 já permite
    // criar os dois saldos; não precisa simular estado legado.
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
    v11b_semConstraintCatalogo();

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
    // Teste de autorização: abort_unless dispara na 1ª linha de fundir(), antes de
    // qualquer DB. Não precisa de duplicata — 2º saldo em depósito distinto evita o UNIQUE.
    $naoAdmin = User::factory()->create(['is_admin' => false]);
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox Secundario', 30.0, 9.0, $catalogo->id);

    expect(fn () => app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $naoAdmin))
        ->toThrow(HttpException::class);
});

// ─── PASSO 2 — Comando estoque:sanear-duplicatas-catalogo ─────────────────────

it('sanear_sem_opcao_falha_pedindo_dry_run_ou_executado_por', function () {
    $this->artisan('estoque:sanear-duplicatas-catalogo')
        ->assertExitCode(2);
});

it('sanear_dry_run_lista_grupos_sem_executar_fusao', function () {
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--dry-run' => true])
        ->assertExitCode(0);

    // Nenhuma fusão: ambos saldos seguem ativos (não viraram tombstone)
    expect(SaldoEstoque::withoutGlobalScopes()->whereNull('fundido_para_id')->count())->toBe(2)
        ->and($saldoA->refresh()->fundido_para_id)->toBeNull()
        ->and($saldoB->refresh()->fundido_para_id)->toBeNull();
});

it('sanear_executa_fusao_com_executado_por_admin', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--executado-por' => $admin->id])
        ->assertExitCode(0);

    // Sobra exatamente 1 saldo ativo (destino) para o grupo; a origem virou tombstone
    $ativos = SaldoEstoque::withoutGlobalScopes()
        ->where('item_catalogo_id', $catalogo->id)
        ->whereNull('fundido_para_id')
        ->get();

    expect($ativos)->toHaveCount(1)
        ->and((float) $ativos->first()->quantidade)->toEqualWithDelta(40.0, 0.001)
        ->and($saldoB->refresh()->fundido_para_id)->toBe($saldoA->id);
});

it('sanear_e_idempotente_segunda_execucao_nao_funde_nada', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--executado-por' => $admin->id])
        ->assertExitCode(0);

    $logsApos1 = SaldoFusaoLog::count();

    // Segunda execução: origens já são tombstone, nada a fundir
    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--executado-por' => $admin->id])
        ->expectsOutputToContain('Nada a sanear')
        ->assertExitCode(0);

    expect(SaldoFusaoLog::count())->toBe($logsApos1);
});

it('sanear_rejeita_executado_por_nao_admin_sem_fundir', function () {
    // Com a validação de Admin no topo do comando, a rejeição independe de haver
    // duplicatas — usamos depósitos distintos (sem grupo duplicado, sem UNIQUE violado).
    $naoAdmin = User::factory()->create(['is_admin' => false]);
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox Secundario', 30.0, 9.0, $catalogo->id);

    $this->artisan('estoque:sanear-duplicatas-catalogo', ['--executado-por' => $naoAdmin->id])
        ->assertExitCode(1);

    // Nada fundido
    expect(SaldoEstoque::withoutGlobalScopes()->whereNull('fundido_para_id')->count())->toBe(2)
        ->and($saldoA->refresh()->fundido_para_id)->toBeNull()
        ->and($saldoB->refresh()->fundido_para_id)->toBeNull();
});

// ─── Consistência interna do ledger de fusão ──────────────────────────────────

it('movimentacoes_fusao_mantem_invariante_quantidade_x_custo_igual_valor', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    // Duplicatas da mesma identidade (unidade/depósito/catálogo) para fundir
    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);

    app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    $movimentos = MovimentacaoEstoque::where('tipo', TipoMovimentacao::Fusao->value)->get();

    expect($movimentos)->not->toBeEmpty();

    // Cada lançamento de fusão deve satisfazer quantidade × custo_unitario ≈ valor_total
    foreach ($movimentos as $mov) {
        $calculado = (float) $mov->quantidade * (float) $mov->custo_unitario;

        expect(abs($calculado - (float) $mov->valor_total))->toBeLessThan(
            0.01,
            "Movimentação de fusão #{$mov->id} inconsistente: ".
            "{$mov->quantidade} × {$mov->custo_unitario} = {$calculado} ≠ {$mov->valor_total}"
        );
    }
});

// ─── PASSO 3 — UNIQUE parcial de identidade de catálogo (constraint LIGADA) ────

it('unique_catalogo_bloqueia_segundo_saldo_ativo_da_mesma_identidade', function () {
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Item Unico']);

    // Primeiro saldo ativo com catálogo: ok
    v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);

    // Segundo saldo ativo com a MESMA (unidade, depósito, catálogo): barrado pelo UNIQUE parcial
    expect(fn () => v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id))
        ->toThrow(QueryException::class);
});

it('unique_catalogo_ignora_avulsos_e_tombstones', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Item Coexistencia']);

    // Avulsos (catálogo NULL) com descrições distintas coexistem no mesmo depósito
    v11b_criarSaldo($unidade, 'Parafuso', 'Almox');
    v11b_criarSaldo($unidade, 'Prego', 'Almox');
    expect(SaldoEstoque::withoutGlobalScopes()->whereNull('item_catalogo_id')->count())->toBe(2);

    // Destino + tombstone da mesma identidade coexistem (predicado fundido_para_id IS NULL):
    // estado legado → cria duplicatas → funde → recria o índice (deploy pós-saneamento).
    v11b_semConstraintCatalogo();
    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'AlmoxFusao', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'AlmoxFusao', 30.0, 9.0, $catalogo->id);
    app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    // Recriar o índice NÃO deve falhar: o tombstone está fora do predicado parcial
    DB::statement(
        'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque '
        .'(unidade_id, deposito, item_catalogo_id) '
        .'WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
    );

    $daIdentidade = SaldoEstoque::withoutGlobalScopes()->where('item_catalogo_id', $catalogo->id)->get();
    expect($daIdentidade)->toHaveCount(2)
        ->and($daIdentidade->whereNull('fundido_para_id')->count())->toBe(1);
});

// ─── Pós-sec/QA — fechamento de lacunas do Passo 3 ────────────────────────────

it('vincular_nao_e_bloqueado_por_tombstone_da_mesma_identidade', function () {
    // BUG-01: o pré-check de colisão deve ignorar tombstones. Cenário: funde dois saldos
    // (sobra destino + tombstone), desvincula o destino e, então, vincula um avulso ao
    // mesmo catálogo/depósito — só o tombstone tem o catálogo, e ele não pode bloquear.
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Item Reconciliacao']);
    v11b_semConstraintCatalogo();

    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 30.0, 9.0, $catalogo->id);
    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB], $admin);

    // Desvincula o destino → em 'Almox' o catálogo C só existe no tombstone (saldoB)
    app(ConfirmarVinculoSaldoAction::class)->desvincular($destino, $admin);

    $avulso = v11b_criarSaldo($unidade, 'Avulso C', 'Almox', 4.0, 2.0);
    $vinculado = app(ConfirmarVinculoSaldoAction::class)->vincular($avulso, $catalogo, $admin);

    expect($vinculado->item_catalogo_id)->toBe($catalogo->id);
});

it('vincular_em_corrida_de_unicidade_converte_para_validation_exception', function () {
    // Catch da ConfirmarVinculoSaldoAction: o pré-check passa, mas outro Admin ocupa o slot
    // do UNIQUE entre o check e o UPDATE → a violação do banco vira ValidationException (não 500).
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create(['descricao' => 'Item Corrida Vinculo']);

    $avulso = v11b_criarSaldo($unidade, 'Avulso Alvo', 'Almox', 5.0, 4.0);

    $dispatcher = SaldoEstoque::getEventDispatcher();
    $comListener = clone $dispatcher;
    $competidorCriado = false;
    $comListener->listen('eloquent.updating: '.SaldoEstoque::class, function (SaldoEstoque $s) use (&$competidorCriado, $unidade, $catalogo) {
        if ($competidorCriado || $s->item_catalogo_id !== $catalogo->id) {
            return;
        }
        $competidorCriado = true;
        SaldoEstoque::withoutEvents(fn () => SaldoEstoque::create([
            'unidade_id' => $unidade->id,
            'deposito' => 'Almox',
            'descricao_item' => 'Concorrente',
            'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Concorrente'),
            'unidade_medida' => 'un',
            'quantidade' => 1.0,
            'custo_medio_ponderado' => 2.0,
            'valor_total' => 2.0,
            'item_catalogo_id' => $catalogo->id,
        ]));
    });
    SaldoEstoque::setEventDispatcher($comListener);

    try {
        expect(fn () => app(ConfirmarVinculoSaldoAction::class)->vincular($avulso, $catalogo, $admin))
            ->toThrow(ValidationException::class);
    } finally {
        SaldoEstoque::setEventDispatcher($dispatcher);
    }
});

it('fusao_de_tres_saldos_soma_quantidade_e_calcula_cmp_ponderado', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();
    v11b_semConstraintCatalogo();

    // 10@5 (50) + 20@8 (160) + 30@10 (300) = 60un, valor 510, CMP 8.5
    $saldoA = v11b_criarSaldo($unidade, 'Item A', 'Almox', 10.0, 5.0, $catalogo->id);
    $saldoB = v11b_criarSaldo($unidade, 'Item B', 'Almox', 20.0, 8.0, $catalogo->id);
    $saldoC = v11b_criarSaldo($unidade, 'Item C', 'Almox', 30.0, 10.0, $catalogo->id);

    $destino = app(FusaoSaldosAction::class)->fundir([$saldoA, $saldoB, $saldoC], $admin);

    expect((float) $destino->quantidade)->toEqualWithDelta(60.0, 0.001)
        ->and((float) $destino->custo_medio_ponderado)->toEqualWithDelta(8.5, 0.0001)
        ->and((float) $destino->valor_total)->toEqualWithDelta(510.0, 0.01);

    // Dois tombstones (B e C), um log por origem
    expect(SaldoFusaoLog::count())->toBe(2)
        ->and($saldoB->refresh()->fundido_para_id)->toBe($destino->id)
        ->and($saldoC->refresh()->fundido_para_id)->toBe($destino->id);
});
