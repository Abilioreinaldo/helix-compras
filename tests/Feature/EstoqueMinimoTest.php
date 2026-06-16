<?php

use App\Actions\DefinirEstoqueMinimoAction;
use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\EstoqueMinimo;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Cria um almoxarife vinculado a uma unidade.
 */
function criarAlmoxarifeDaUnidade(Unidade $unidade): User
{
    $usuario = User::factory()->create();
    $unidade->usuarios()->attach($usuario->id, ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null]);

    return $usuario;
}

/**
 * Cria um saldo de catálogo em uma unidade (sem fluxo de compra).
 */
function emMinimo_criarSaldo(
    Unidade $unidade,
    CatalogoItem $item,
    float $quantidade,
    string $deposito = 'Depósito Central',
    ?int $fundidoParaId = null
): SaldoEstoque {
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao),
        'unidade_medida' => $item->unidade_medida ?? 'un',
        'quantidade' => $quantidade,
        'custo_medio_ponderado' => 10,
        'valor_total' => $quantidade * 10,
        'item_catalogo_id' => $item->id,
        'fundido_para_id' => $fundidoParaId,
    ]);
}

// ─── PASSO 1 — DefinirEstoqueMinimoAction ─────────────────────────────────────

describe('DefinirEstoqueMinimoAction', function () {

    test('define cria registro quando não existe', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);

        expect($resultado)->toBeInstanceOf(EstoqueMinimo::class)
            ->and((float) $resultado->quantidade_minima)->toBe(10.0)
            ->and(EstoqueMinimo::count())->toBe(1);
    });

    test('redefine atualiza o registro existente (último vence)', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 25.0, $admin);

        expect(EstoqueMinimo::count())->toBe(1)
            ->and((float) $resultado->quantidade_minima)->toBe(25.0);
    });

    test('quantidade zero remove registro existente e retorna null', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 0.0, $admin);

        expect($resultado)->toBeNull()
            ->and(EstoqueMinimo::count())->toBe(0);
    });

    test('quantidade negativa remove registro existente e retorna null', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, -5.0, $admin);

        expect($resultado)->toBeNull()
            ->and(EstoqueMinimo::count())->toBe(0);
    });

    test('almoxarife da própria unidade pode definir mínimo', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $almoxarife = criarAlmoxarifeDaUnidade($unidade);

        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 5.0, $almoxarife);

        expect($resultado)->toBeInstanceOf(EstoqueMinimo::class);
    });

    test('admin pode definir mínimo em qualquer unidade', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        $resultado = app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 8.0, $admin);

        expect($resultado)->toBeInstanceOf(EstoqueMinimo::class);
    });

    test('almoxarife de outra unidade é barrado', function () {
        $unidade1 = Unidade::factory()->create();
        $unidade2 = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $almoxarife = criarAlmoxarifeDaUnidade($unidade2);

        expect(fn () => app(DefinirEstoqueMinimoAction::class)->execute($unidade1, $item, 10.0, $almoxarife))
            ->toThrow(ValidationException::class);
    });

    test('solicitante é barrado', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $solicitante = User::factory()->create();
        $unidade->usuarios()->attach($solicitante->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);

        expect(fn () => app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $solicitante))
            ->toThrow(ValidationException::class);
    });

    test('item inativo é barrado', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->inativo()->create();
        $admin = User::factory()->admin()->create();

        expect(fn () => app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin))
            ->toThrow(ValidationException::class);
    });

    test('item soft-deletado é barrado', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();
        $item->delete();

        expect(fn () => app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin))
            ->toThrow(ValidationException::class);
    });

});

// ─── PASSO 2 — EstoqueMinimo::itensAReporPara e itemCatalogoIdsEmAlerta ───────

describe('EstoqueMinimo::itensAReporPara', function () {

    test('saldo zero aparece na lista (sugestão = mínimo)', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 0.0);

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(1)
            ->and((float) $resultado->first()->saldo_atual)->toBe(0.0)
            ->and((float) $resultado->first()->quantidade_sugerida)->toBe(10.0);
    });

    test('saldo inexistente (sem linha em saldos_estoque) aparece na lista', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 5.0, $admin);
        // Sem criar saldo — LEFT JOIN retorna NULL → COALESCE(NULL, 0) = 0

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(1)
            ->and((float) $resultado->first()->saldo_atual)->toBe(0.0)
            ->and((float) $resultado->first()->quantidade_sugerida)->toBe(5.0);
    });

    test('multi-depósito: dois depósitos abaixo do mínimo somam e geram 1 alerta', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 3.0, 'Depósito A');
        emMinimo_criarSaldo($unidade, $item, 4.0, 'Depósito B');
        // Total = 7 < 10 → alerta

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(1)
            ->and((float) $resultado->first()->saldo_atual)->toBe(7.0)
            ->and((float) $resultado->first()->quantidade_sugerida)->toBe(3.0);
    });

    test('multi-depósito: soma igual ao mínimo não gera alerta', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 5.0, 'Depósito A');
        emMinimo_criarSaldo($unidade, $item, 5.0, 'Depósito B');
        // Total = 10 = mínimo → NÃO alerta (estrito)

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(0);
    });

    test('saldo igual ao mínimo não gera alerta (alerta é estritamente menor)', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 5.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 5.0);

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(0);
    });

    test('tombstone (fundido_para_id não nulo) é ignorado na soma', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        $saldoAtivo = emMinimo_criarSaldo($unidade, $item, 6.0);
        // tombstone: fundido_para_id aponta para o saldo ativo
        emMinimo_criarSaldo($unidade, $item, 20.0, 'Depósito B', $saldoAtivo->id);
        // Somente saldo ativo conta: 6 < 10 → alerta

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(1)
            ->and((float) $resultado->first()->saldo_atual)->toBe(6.0);
    });

    test('saldo avulso (item_catalogo_id null) é ignorado', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        // Saldo avulso (sem catálogo) — não deve ser somado
        SaldoEstoque::create([
            'unidade_id' => $unidade->id,
            'deposito' => 'Depósito Central',
            'descricao_item' => 'Item avulso',
            'descricao_normalizada' => 'item avulso',
            'unidade_medida' => 'un',
            'quantidade' => 100.0,
            'custo_medio_ponderado' => 10,
            'valor_total' => 1000,
            'item_catalogo_id' => null,
        ]);

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(1)
            ->and((float) $resultado->first()->saldo_atual)->toBe(0.0);
    });

    test('item inativo de catálogo é escondido do painel', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 0.0);

        $item->update(['ativo' => false]);

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(0);
    });

    test('item soft-deletado de catálogo é escondido do painel', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 0.0);

        $item->delete();

        $resultado = EstoqueMinimo::itensAReporPara($admin);

        expect($resultado)->toHaveCount(0);
    });

    test('visibilidade: almoxarife só vê a própria unidade', function () {
        $unidade1 = Unidade::factory()->create();
        $unidade2 = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();
        $almoxarife = criarAlmoxarifeDaUnidade($unidade1);

        app(DefinirEstoqueMinimoAction::class)->execute($unidade1, $item, 10.0, $admin);
        app(DefinirEstoqueMinimoAction::class)->execute($unidade2, $item, 10.0, $admin);

        $resultadoAlmoxarife = EstoqueMinimo::itensAReporPara($almoxarife);
        $resultadoAdmin = EstoqueMinimo::itensAReporPara($admin);

        expect($resultadoAlmoxarife)->toHaveCount(1)
            ->and((int) $resultadoAlmoxarife->first()->unidade_id)->toBe($unidade1->id)
            ->and($resultadoAdmin)->toHaveCount(2);
    });

    test('visibilidade: compradora vê a rede inteira', function () {
        $unidade1 = Unidade::factory()->create();
        $unidade2 = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();
        $compradora = User::factory()->compradora()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade1, $item, 10.0, $admin);
        app(DefinirEstoqueMinimoAction::class)->execute($unidade2, $item, 10.0, $admin);

        $resultado = EstoqueMinimo::itensAReporPara($compradora);

        expect($resultado)->toHaveCount(2);
    });

    test('usuário sem perfil retorna coleção vazia', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();
        $semPerfil = User::factory()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);

        $resultado = EstoqueMinimo::itensAReporPara($semPerfil);

        expect($resultado)->toHaveCount(0);
    });

});

describe('EstoqueMinimo::itemCatalogoIdsEmAlerta', function () {

    test('retorna ids dos itens em alerta nas unidades informadas', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 10.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 3.0);

        $ids = EstoqueMinimo::itemCatalogoIdsEmAlerta([$unidade->id]);

        expect($ids)->toContain($item->id);
    });

    test('retorna vazio quando unidadeIds está vazio', function () {
        $ids = EstoqueMinimo::itemCatalogoIdsEmAlerta([]);

        expect($ids)->toBeEmpty();
    });

    test('não inclui item com saldo suficiente', function () {
        $unidade = Unidade::factory()->create();
        $item = CatalogoItem::factory()->create();
        $admin = User::factory()->admin()->create();

        app(DefinirEstoqueMinimoAction::class)->execute($unidade, $item, 5.0, $admin);
        emMinimo_criarSaldo($unidade, $item, 10.0);

        $ids = EstoqueMinimo::itemCatalogoIdsEmAlerta([$unidade->id]);

        expect($ids)->toBeEmpty();
    });

});
