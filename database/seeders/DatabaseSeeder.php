<?php

namespace Database\Seeders;

use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Cria o tenant (via UserFactory) + usuários iniciais.
        $this->call(UsuarioSeeder::class);

        // Domínio semeado DENTRO do contexto do tenant: no console não há request
        // autenticado, então sem runFor o BelongsToTenant carimbaria tenant_id null
        // e os dados ficariam invisíveis no app. Banco (global COMPE) ignora o contexto.
        $tenantId = Tenant::query()->orderBy('created_at')->value('id');

        TenantContext::runFor($tenantId, function () {
            $this->call([
                UnidadeSeeder::class,
                CatalogoItemSeeder::class,
                FornecedorSeeder::class,
                BancoSeeder::class,
                CentroCustoSeeder::class,
                RequisicaoSeeder::class,
                CargaMediaSeeder::class,
            ]);
        });
    }
}
