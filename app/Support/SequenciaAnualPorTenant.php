<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Numeração sequencial ANUAL e POR TENANT (pedidos de compra, requisições).
 *
 * Cada tabela de sequência tem PK composta (tenant_id, ano) + ultimo_numero. O
 * próximo número é obtido com lock pessimista na linha do tenant (deve rodar
 * dentro da transação do chamador) — dois tenants nunca disputam a mesma linha
 * nem enxergam o volume um do outro pela numeração.
 */
class SequenciaAnualPorTenant
{
    /** Reserva e devolve o próximo número da sequência (tenant, ano). */
    public function proximo(string $tabela, string $tenantId, int $ano): int
    {
        $chave = ['tenant_id' => $tenantId, 'ano' => $ano];

        $seq = DB::table($tabela)->where($chave)->lockForUpdate()->first();

        if ($seq === null) {
            DB::table($tabela)->insertOrIgnore($chave + [
                'ultimo_numero' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seq = DB::table($tabela)->where($chave)->lockForUpdate()->first();
        }

        $proximo = (int) $seq->ultimo_numero + 1;

        DB::table($tabela)->where($chave)->update([
            'ultimo_numero' => $proximo,
            'updated_at' => now(),
        ]);

        return $proximo;
    }
}
