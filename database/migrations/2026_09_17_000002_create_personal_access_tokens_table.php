<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabela do Sanctum (tokens de API da identidade).
 *
 * O Compras não expõe API hoje, mas a identidade da fundação usa HasApiTokens e,
 * desde a v0.2.0, UserService::changeStatus/deleteUser/removeMembership REVOGAM os
 * tokens do usuário (helix/foundation UserService::revokeTokens). Sem a tabela,
 * inativar/excluir um usuário quebra com "no such table: personal_access_tokens".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Criada só se ainda não existir: em produção o banco de IDENTIDADE é
        // compartilhado entre o console e os apps, e cada app publica esta migration
        // com nome próprio — o `create` puro reprovava o deploy com "table already
        // exists" (mesmo padrão das tabelas de cache e fila do console).
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
