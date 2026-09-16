<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link assinado de resposta de cotação (decisão 11 — parecer OWASP ASVS).
 *
 * O link é o ÚNICO canal que grava a proposta do fornecedor. Uma linha por emissão
 * (cotação × fornecedor); reemitir revoga a anterior.
 *
 *  - `token_hash`: SHA-256 (hex) do token de 256 bits. O token em claro só existe
 *    no e-mail enviado — um dump da base não entrega link utilizável.
 *  - `referencia`: ULID PÚBLICO que vai no assunto `[COT-…]` só para CORRELACIONAR a
 *    resposta por e-mail com a cotação (vira aviso ao comprador). Não autoriza nada.
 *  - `expires_at`: prazo de resposta da cotação; `submetido_em`: uso único;
 *    `revogado_em`: revogação explícita ou por reemissão.
 *
 * Os dois uniques são GLOBAIS por desenho: é por eles que o link/resposta DESCOBRE o
 * tenant (a rota pública e a caixa IMAP não têm tenant no contexto).
 *
 * Idempotente (não recria a tabela) e com down().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotacao_links')) {
            return;
        }

        Schema::create('cotacao_links', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cotacao_id')->constrained('cotacoes')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->char('token_hash', 64);
            $table->char('referencia', 26);
            $table->timestamp('expires_at');
            $table->timestamp('submetido_em')->nullable();
            $table->timestamp('revogado_em')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revogado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('token_hash', 'cotacao_links_token_hash_uq');
            $table->unique('referencia', 'cotacao_links_referencia_uq');
            $table->index(['tenant_id', 'cotacao_id'], 'cotacao_links_tenant_cotacao_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_links');
    }
};
