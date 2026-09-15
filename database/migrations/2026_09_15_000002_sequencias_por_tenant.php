<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numeração sequencial POR TENANT (auditoria multitenant 2026-09-15).
 *
 * - `sequencias_pedido_compra`: PK deixa de ser `ano` (contador global da rede —
 *   tenant B "consumia" números de A e via o volume dele) e passa a (tenant_id, ano).
 *   Trocar PK não é suportado in-place no SQLite: a tabela é recriada e as linhas
 *   existentes migram para o 1º tenant (a instalação era mono-tenant por design —
 *   mesmo critério do backfill de 2026_08_04).
 * - `sequencias_requisicao`: nova — o código REQ-AAAA-NNNNNN deixa de derivar do id
 *   global da requisição e passa a ser sequência anual do tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $primeiro = DB::table('tenants')->orderBy('created_at')->value('id');
        $linhas = DB::table('sequencias_pedido_compra')->get();

        Schema::dropIfExists('sequencias_pedido_compra');

        Schema::create('sequencias_pedido_compra', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->primary(['tenant_id', 'ano']);
        });

        if ($primeiro !== null) {
            foreach ($linhas as $linha) {
                DB::table('sequencias_pedido_compra')->insert([
                    'tenant_id' => $primeiro,
                    'ano' => $linha->ano,
                    'ultimo_numero' => $linha->ultimo_numero,
                    'created_at' => $linha->created_at,
                    'updated_at' => $linha->updated_at,
                ]);
            }
        }

        Schema::create('sequencias_requisicao', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->primary(['tenant_id', 'ano']);
        });

        // Semente por tenant/ano: o maior sufixo já emitido (REQ-AAAA-NNNNNN) para não
        // colidir com códigos legados derivados do id global.
        $maximos = DB::table('requisicoes')
            ->whereNotNull('codigo')
            ->whereNotNull('tenant_id')
            ->where('codigo', 'like', 'REQ-____-%')
            ->get(['tenant_id', 'codigo']);

        $sementes = [];
        foreach ($maximos as $req) {
            if (! preg_match('/^REQ-(\d{4})-(\d+)$/', $req->codigo, $m)) {
                continue;
            }
            $chave = $req->tenant_id.'|'.$m[1];
            $sementes[$chave] = max($sementes[$chave] ?? 0, (int) $m[2]);
        }

        foreach ($sementes as $chave => $ultimo) {
            [$tenantId, $ano] = explode('|', $chave);
            DB::table('sequencias_requisicao')->insert([
                'tenant_id' => $tenantId,
                'ano' => (int) $ano,
                'ultimo_numero' => $ultimo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sequencias_requisicao');

        $linhas = DB::table('sequencias_pedido_compra')
            ->selectRaw('ano, MAX(ultimo_numero) as ultimo_numero')
            ->groupBy('ano')
            ->get();

        Schema::dropIfExists('sequencias_pedido_compra');

        Schema::create('sequencias_pedido_compra', function (Blueprint $table) {
            $table->unsignedSmallInteger('ano')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        foreach ($linhas as $linha) {
            DB::table('sequencias_pedido_compra')->insert([
                'ano' => $linha->ano,
                'ultimo_numero' => $linha->ultimo_numero,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
