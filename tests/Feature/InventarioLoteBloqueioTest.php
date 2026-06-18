<?php

use App\Actions\AbrirSessaoInventarioAction;
use App\Actions\AjusteEstoqueAction;
use App\Actions\AplicarInventarioAction;
use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Saldo com controle de lote (catálogo controla_lote=true), já com 1 lote cobrindo o saldo. */
function invlote_saldoComLote(Unidade $unidade, string $deposito = 'Depósito A', float $qtd = 10.0): SaldoEstoque
{
    $catalogo = CatalogoItem::factory()->create([
        'descricao' => 'Insumo com Lote '.fake()->unique()->numerify('###'),
        'controla_lote' => true,
    ]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => $qtd,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => $qtd * 10.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    LoteEstoque::factory()->create([
        'saldo_estoque_id' => $saldo->id,
        'numero_lote' => 'L-1',
        'validade' => '2027-01-01',
        'quantidade' => $qtd,
        'fundido_para_id' => null,
    ]);

    return $saldo;
}

/** Saldo legado sem catálogo/lote (comportamento v1). */
function invlote_saldoSemLote(Unidade $unidade, string $deposito = 'Depósito A', float $qtd = 20.0): SaldoEstoque
{
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => 'Material Sem Lote',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Material Sem Lote'),
        'unidade_medida' => 'un',
        'quantidade' => $qtd,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => $qtd * 10.0,
    ]);
}

function invlote_almoxarife(Unidade $unidade): User
{
    $user = User::factory()->create();
    $user->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);

    return $user;
}

// ─── AjusteEstoqueAction: bloqueia item controla_lote ─────────────────────────

it('ajuste_positivo_em_saldo_controla_lote_e_bloqueado_sem_alterar_nada', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);
    $saldo = invlote_saldoComLote($unidade, qtd: 10.0);

    expect(fn () => app(AjusteEstoqueAction::class)->execute(
        $saldo, TipoMovimentacao::AjustePositivo, 5.0, 'Tentativa', $almoxarife
    ))->toThrow(ValidationException::class);

    // Invariante intacta: saldo e lote inalterados, nenhuma movimentação.
    expect((float) $saldo->refresh()->quantidade)->toBe(10.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe(10.0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('ajuste_negativo_em_saldo_controla_lote_e_bloqueado_sem_alterar_nada', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);
    $saldo = invlote_saldoComLote($unidade, qtd: 10.0);

    expect(fn () => app(AjusteEstoqueAction::class)->execute(
        $saldo, TipoMovimentacao::AjusteNegativo, 3.0, 'Tentativa', $almoxarife
    ))->toThrow(ValidationException::class);

    expect((float) $saldo->refresh()->quantidade)->toBe(10.0)
        ->and((float) $saldo->lotesVivos()->sum('quantidade'))->toBe(10.0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});

it('ajuste_em_saldo_sem_controle_de_lote_continua_funcionando', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);
    $saldo = invlote_saldoSemLote($unidade, qtd: 20.0);

    $mov = app(AjusteEstoqueAction::class)->execute(
        $saldo, TipoMovimentacao::AjustePositivo, 5.0, 'Correção', $almoxarife
    );

    expect($mov->tipo)->toBe(TipoMovimentacao::AjustePositivo)
        ->and((float) $saldo->refresh()->quantidade)->toBe(25.0);
});

// ─── AbrirSessaoInventarioAction: exclui controla_lote do snapshot ────────────

it('abrir_inventario_exclui_saldos_controla_lote_do_snapshot', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);

    $semLote = invlote_saldoSemLote($unidade);
    $comLote = invlote_saldoComLote($unidade);

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($unidade, 'Depósito A', $almoxarife);

    $idsNoSnap = $sessao->itens()->pluck('saldo_estoque_id')->all();

    expect($sessao->itens()->count())->toBe(1)                  // só o sem lote
        ->and(in_array($semLote->id, $idsNoSnap))->toBeTrue()
        ->and(in_array($comLote->id, $idsNoSnap))->toBeFalse();
});

it('abrir_inventario_so_com_itens_controla_lote_abre_sessao_vazia', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);

    invlote_saldoComLote($unidade, qtd: 10.0);
    invlote_saldoComLote($unidade, qtd: 5.0);

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($unidade, 'Depósito A', $almoxarife);

    expect($sessao->itens()->count())->toBe(0);
});

// ─── AplicarInventarioAction: guard defensivo (toggle pós-snapshot) ───────────

it('aplicar_inventario_recusa_se_item_virou_controla_lote_apos_snapshot', function () {
    $unidade = Unidade::factory()->create();
    $almoxarife = invlote_almoxarife($unidade);

    // Saldo vinculado a um catálogo que começa SEM controle de lote → entra no snapshot.
    $catalogo = CatalogoItem::factory()->create(['controla_lote' => false]);
    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito A',
        'descricao_item' => $catalogo->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($catalogo->descricao),
        'unidade_medida' => 'un',
        'quantidade' => 10.0,
        'custo_medio_ponderado' => 10.0,
        'valor_total' => 100.0,
        'item_catalogo_id' => $catalogo->id,
    ]);

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($unidade, 'Depósito A', $almoxarife);
    expect($sessao->itens()->count())->toBe(1);

    $sessao->itens->each(fn ($item) => $item->update(['quantidade_contada' => 12.0]));
    $sessao->load('itens');

    // Toggle após o snapshot: agora o item controla lote → guard defensivo recusa.
    $catalogo->update(['controla_lote' => true]);

    expect(fn () => app(AplicarInventarioAction::class)->execute($sessao, 'Justificativa.', $almoxarife))
        ->toThrow(ValidationException::class);

    // Nada aplicado: saldo intacto, nenhuma movimentação.
    expect((float) $saldo->refresh()->quantidade)->toBe(10.0)
        ->and(MovimentacaoEstoque::count())->toBe(0);
});
