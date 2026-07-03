<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Harness do índice UNIQUE de catálogo (`saldos_estoque_catalogo_unique`) para os testes de
 * fusão/saneamento (FaseV11A/B), que simulam o estado legado dropando/recriando o índice NO
 * MEIO do teste.
 *
 * Esse padrão só isola em SQLite: no MySQL o DDL faz commit implícito e fura o
 * RefreshDatabase (vira espera de lock/hang). Por isso estes casos são **SQLite-only** — a
 * semântica do UNIQUE de catálogo em MySQL é coberta pelo `SaldoCatalogoUnicoTest` (portável,
 * sem DDL no meio do teste). Chamado num driver != sqlite, o harness PULA o teste.
 */
function harnessDropIndiceCatalogoSaldos(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('Muta índice no meio do teste — SQLite-only (A2 em MySQL: ver SaldoCatalogoUnicoTest).');
    }

    DB::statement('DROP INDEX IF EXISTS saldos_estoque_catalogo_unique');
}

/**
 * Recria o índice UNIQUE de catálogo (pós-saneamento) — par do
 * {@see harnessDropIndiceCatalogoSaldos()}. Mesma regra: SQLite-only.
 */
function harnessCriaIndiceCatalogoSaldos(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('Muta índice no meio do teste — SQLite-only.');
    }

    DB::statement(
        'CREATE UNIQUE INDEX saldos_estoque_catalogo_unique ON saldos_estoque '
        .'(unidade_id, deposito, item_catalogo_id) '
        .'WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL'
    );
}
