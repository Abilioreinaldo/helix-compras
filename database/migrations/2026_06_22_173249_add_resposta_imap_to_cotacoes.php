<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) valor passa a ser nullable: a cotação pode ser criada no envio do
        //    pedido ao fornecedor (advisory), antes do preço ser conhecido/confirmado.
        //    Em call separado: no SQLite o change() reconstrói a tabela; misturar com
        //    add-column/index no mesmo closure é frágil.
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->decimal('valor', 15, 2)->nullable()->change();
        });

        // 2) Colunas advisory (sugestão extraída do e-mail do fornecedor — NUNCA é o
        //    valor oficial; a compradora confirma). after() é no-op no SQLite.
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->decimal('valor_respondido', 15, 2)->nullable()->after('valor');
            $table->integer('prazo_respondido')->nullable()->after('valor_respondido');
            $table->text('observacoes_fornecedor')->nullable()->after('prazo_respondido');
            $table->dateTime('resposta_recebida_em')->nullable()->after('observacoes_fornecedor');
            $table->string('email_externo_id')->nullable()->after('resposta_recebida_em');
        });

        // 3) Índices. O unique no message-id garante idempotência (1 e-mail = 1 registro)
        //    e já serve de índice de busca — não precisa de índice extra na mesma coluna.
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->unique('email_externo_id');
            $table->index('resposta_recebida_em');
        });
    }

    public function down(): void
    {
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->dropUnique(['email_externo_id']);
            $table->dropIndex(['resposta_recebida_em']);
        });

        Schema::table('cotacoes', function (Blueprint $table) {
            $table->dropColumn([
                'valor_respondido',
                'prazo_respondido',
                'observacoes_fornecedor',
                'resposta_recebida_em',
                'email_externo_id',
            ]);
        });

        // NÃO restauramos valor para NOT NULL: pode haver cotações advisory com valor
        // null. Restaurar quebraria o rollback — forward-fix se necessário (padrão do projeto).
    }
};
