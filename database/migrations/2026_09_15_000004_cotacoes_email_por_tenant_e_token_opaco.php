<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Captura IMAP de cotações: idempotência POR TENANT e token opaco no assunto
 * (auditoria adversarial, achado MÉDIO).
 *
 * 1) `cotacoes.email_externo_id` era UNIQUE GLOBAL. Como a caixa é única da
 *    instalação e o Message-ID é escolhido pelo servidor do fornecedor, uma
 *    resposta legítima do tenant B era DESCARTADA em silêncio quando o mesmo
 *    Message-ID já existia no tenant A (colisão acidental — ou plantada). A
 *    chave natural correta é (tenant_id, email_externo_id).
 *
 * 2) O assunto levava `[COT-{id}]`, uma PK sequencial GLOBAL: revela a outra
 *    empresa o volume de cotações da instalação e é enumerável. Passa a levar um
 *    token opaco (ULID) por cotação — `email_token`, semeado aqui para o legado.
 *
 * Aditiva e idempotente (pode rodar sobre base já migrada); down completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cotacoes', 'email_token')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->string('email_token', 32)->nullable()->after('email_externo_id');
            });
        }

        // Legado: uma cotação sem token nunca casaria uma resposta nova.
        DB::table('cotacoes')->whereNull('email_token')->orderBy('id')
            ->select('id')->get()
            ->each(fn ($linha) => DB::table('cotacoes')->where('id', $linha->id)
                ->update(['email_token' => (string) Str::ulid()]));

        if (! Schema::hasIndex('cotacoes', 'cotacoes_email_token_uq')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->unique('email_token', 'cotacoes_email_token_uq');
            });
        }

        if (Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropUnique('cotacoes_email_externo_id_unique');
            });
        }

        if (! Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->unique(['tenant_id', 'email_externo_id'], 'cotacoes_tenant_email_externo_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('cotacoes', 'cotacoes_tenant_email_externo_uq')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropUnique('cotacoes_tenant_email_externo_uq');
            });
        }

        if (! Schema::hasIndex('cotacoes', 'cotacoes_email_externo_id_unique')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->unique('email_externo_id', 'cotacoes_email_externo_id_unique');
            });
        }

        if (Schema::hasIndex('cotacoes', 'cotacoes_email_token_uq')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropUnique('cotacoes_email_token_uq');
            });
        }

        if (Schema::hasColumn('cotacoes', 'email_token')) {
            Schema::table('cotacoes', function (Blueprint $table) {
                $table->dropColumn('email_token');
            });
        }
    }
};
