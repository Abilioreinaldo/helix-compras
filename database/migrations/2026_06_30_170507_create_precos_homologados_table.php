<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos_homologados', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('item_catalogo_id')->constrained('catalogo_itens')->restrictOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->decimal('preco', 12, 2);
            $table->boolean('preferencial')->default(false);
            $table->date('validade_inicio');
            $table->date('validade_fim');
            $table->boolean('ativo')->default(true);
            $table->string('observacao', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['item_catalogo_id', 'ativo']);
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->boolean('expressa')->default(false)->after('is_emergencial');
        });

        Schema::table('cotacoes', function (Blueprint $table) {
            // manual | email | homologado — string em vez de enum nativo para
            // portabilidade SQLite↔MySQL (ALTER de enum é ponto cego entre dialetos).
            $table->string('origem', 20)->default('manual')->after('requisicao_id');
        });
    }

    public function down(): void
    {
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->dropColumn('origem');
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn('expressa');
        });

        Schema::dropIfExists('precos_homologados');
    }
};
