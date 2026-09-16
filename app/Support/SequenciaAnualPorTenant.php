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
 *
 * ORDEM IMPORTA: `insertOrIgnore` PRIMEIRO, `lockForUpdate` depois — sempre sobre
 * uma linha que já existe. Travar uma linha INEXISTENTE faz o InnoDB tomar um gap
 * lock na faixa do índice, que é compartilhada por tenants vizinhos: dois tenants
 * estreando a sequência no mesmo ano bloqueiam um ao outro e deadlockam. Mesmo
 * padrão do CRM (ProposalService::generateNumber).
 */
class SequenciaAnualPorTenant
{
    /** Reserva e devolve o próximo número da sequência (tenant, ano). */
    public function proximo(string $tabela, string $tenantId, int $ano): int
    {
        // Garante a existência da linha SEM travar faixa (no-op se já existe).
        // `tenant_id` no payload é a própria CHAVE da sequência (a tabela não tem
        // model nem BelongsToTenant: a linha É o par tenant+ano) — por isso este
        // arquivo está na allowlist de `no_mass_tenant_writes` do kit.
        DB::table($tabela)->insertOrIgnore([
            'tenant_id' => $tenantId,
            'ano' => $ano,
            'ultimo_numero' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Só então o lock pessimista, agora sobre uma linha existente: o bloqueio
        // é de REGISTRO (a linha deste tenant), nunca de gap entre tenants.
        $seq = DB::table($tabela)
            ->where('tenant_id', $tenantId)
            ->where('ano', $ano)
            ->lockForUpdate()
            ->first();

        $proximo = (int) $seq->ultimo_numero + 1;

        DB::table($tabela)
            ->where('tenant_id', $tenantId)
            ->where('ano', $ano)
            ->update([
                'ultimo_numero' => $proximo,
                'updated_at' => now(),
            ]);

        return $proximo;
    }
}
