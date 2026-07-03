<?php

use App\Models\CatalogoItem;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| A2 — UNIQUE de identidade de catálogo em saldos_estoque (PORTÁVEL).
|--------------------------------------------------------------------------
|
| Valida a constraint que a migration `add_unique_catalogo_to_saldos_estoque` cria —
| SQLite via índice parcial, MySQL via coluna gerada STORED — SEM manipular o índice no
| meio do teste (diferente dos FaseV11A/B, que são SQLite-only por causa do commit implícito
| de DDL no MySQL). Roda nos DOIS bancos: em MySQL real é a evidência do item A2 do checklist.
|
| A unique LEGADA é (unidade_id, deposito, descricao_normalizada); por isso variamos a
| descrição para isolar apenas a constraint de catálogo.
|
*/

uses(RefreshDatabase::class);

/** @return SaldoEstoque saldo criado com a identidade de catálogo dada */
function a2_saldo(Unidade $unidade, string $deposito, string $descricao, ?int $catalogoId, ?int $fundidoParaId = null): SaldoEstoque
{
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => $deposito,
        'descricao_item' => $descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($descricao),
        'unidade_medida' => 'un',
        'quantidade' => 1,
        'custo_medio_ponderado' => 1,
        'valor_total' => 1,
        'item_catalogo_id' => $catalogoId,
        'fundido_para_id' => $fundidoParaId,
    ]);
}

it('barra dois saldos ATIVOS com a mesma identidade de catálogo (A2)', function () {
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    a2_saldo($unidade, 'AlmoxA2', 'Item Alpha', $catalogo->id);

    // Mesmo (unidade, depósito, item_catalogo_id), ambos ativos → o 2º viola o UNIQUE parcial
    // (19 no SQLite, 1062 no MySQL). Descrição diferente evita a unique legada.
    expect(fn () => a2_saldo($unidade, 'AlmoxA2', 'Item Beta', $catalogo->id))
        ->toThrow(QueryException::class);
});

it('permite múltiplos saldos AVULSOS (item_catalogo_id NULL) na mesma unidade/depósito (A2)', function () {
    $unidade = Unidade::factory()->create();

    a2_saldo($unidade, 'AlmoxA2', 'Avulso Um', null);
    a2_saldo($unidade, 'AlmoxA2', 'Avulso Dois', null);

    expect(SaldoEstoque::where('unidade_id', $unidade->id)->whereNull('item_catalogo_id')->count())->toBe(2);
});

it('permite múltiplos TOMBSTONES (fundido_para_id preenchido) com a mesma identidade (A2)', function () {
    $unidade = Unidade::factory()->create();
    $catalogo = CatalogoItem::factory()->create();

    // Destino ativo com o catálogo — única linha DENTRO do escopo do UNIQUE.
    $destino = a2_saldo($unidade, 'AlmoxA2', 'Destino', $catalogo->id);

    // Dois tombstones da MESMA identidade, apontando pro destino → fora do escopo
    // (fundido_para_id != NULL): coexistem com o destino e entre si.
    a2_saldo($unidade, 'AlmoxA2', 'Tomb Um', $catalogo->id, $destino->id);
    a2_saldo($unidade, 'AlmoxA2', 'Tomb Dois', $catalogo->id, $destino->id);

    expect(SaldoEstoque::where('item_catalogo_id', $catalogo->id)->whereNotNull('fundido_para_id')->count())->toBe(2)
        ->and(SaldoEstoque::where('item_catalogo_id', $catalogo->id)->whereNull('fundido_para_id')->count())->toBe(1);
});
