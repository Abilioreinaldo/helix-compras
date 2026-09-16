<?php

use App\Support\SequenciaAnualPorTenant;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Contenção entre tenants na numeração sequencial (auditoria adversarial, MÉDIO).
 *
 * `lockForUpdate()` sobre uma linha que NÃO EXISTE não trava um registro: no InnoDB
 * trava o GAP da faixa do índice, compartilhado pelos tenants vizinhos. Dois tenants
 * estreando a sequência do mesmo ano bloqueavam um ao outro (e deadlockavam) — um
 * tenant degradando o outro, exatamente o que o isolamento proíbe.
 *
 * O gap lock não é observável em SQLite, então o que se prova aqui é a CAUSA: a
 * ordem das operações. A linha é criada com insertOrIgnore ANTES de qualquer lock.
 */
it('não emite lock antes de a linha da sequência existir', function () {
    $tenant = Tenant::create(['slug' => 'seq-a', 'name' => 'Seq A', 'status' => 'active']);

    $consultas = [];
    DB::listen(function ($q) use (&$consultas) {
        if (str_contains($q->sql, 'sequencias_pedido_compra')) {
            $consultas[] = mb_strtolower(mb_substr(trim($q->sql), 0, 6));
        }
    });

    app(SequenciaAnualPorTenant::class)->proximo('sequencias_pedido_compra', (string) $tenant->id, 2026);

    // A PRIMEIRA operação na tabela de sequência é o insert; nenhum select (que em
    // MySQL viria com FOR UPDATE) toca a tabela antes de a linha existir.
    expect($consultas)->not->toBeEmpty()
        ->and($consultas[0])->toBe('insert');
});

it('a numeração de um tenant não depende nem interfere na do outro', function () {
    $a = Tenant::create(['slug' => 'seq-x', 'name' => 'Seq X', 'status' => 'active']);
    $b = Tenant::create(['slug' => 'seq-y', 'name' => 'Seq Y', 'status' => 'active']);

    $seq = app(SequenciaAnualPorTenant::class);

    // Intercalados no mesmo ano: cada tenant tem a própria faixa, do 1 em diante.
    expect($seq->proximo('sequencias_pedido_compra', (string) $a->id, 2026))->toBe(1)
        ->and($seq->proximo('sequencias_pedido_compra', (string) $b->id, 2026))->toBe(1)
        ->and($seq->proximo('sequencias_pedido_compra', (string) $a->id, 2026))->toBe(2)
        ->and($seq->proximo('sequencias_pedido_compra', (string) $b->id, 2026))->toBe(2);
});
