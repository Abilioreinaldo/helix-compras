<?php

namespace Database\Seeders;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\TipoUnidade;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Models\Platform\Identity\TenantFeature;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cenário completo de Compras para o tenant `empresa-a` (base compartilhada da
 * suíte). Idempotente — pode rodar quantas vezes precisar.
 *
 *   php artisan db:seed --class=ComprasTesteEmpresaASeeder
 */
class ComprasTesteEmpresaASeeder extends Seeder
{
    private const SENHA = 'Helix@2024';

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'empresa-a')->first();

        if (! $tenant) {
            $this->command->error('Tenant empresa-a não existe. Crie-o no helix-admin primeiro.');

            return;
        }

        TenantFeature::firstOrCreate(
            ['tenant_id' => $tenant->id, 'feature' => 'compras'],
            ['enabled' => true],
        );

        $admin = $this->usuario($tenant, 'admin.a@comendador.com.br', 'Admin Empresa A', isAdmin: true);
        $hellen = $this->usuario($tenant, 'hellen@comendador.com.br', 'Hellen Costa');
        $joao = $this->usuario($tenant, 'joao.rh@comendador.com.br', 'João Gestor');
        $diretor = $this->usuario($tenant, 'diretor@comendador.com.br', 'Diretor Regional');
        $solicitante = $this->usuario($tenant, 'solicitante@comendador.com.br', 'Carlos Solicitante');
        $almoxarife = $this->usuario($tenant, 'almoxarife@comendador.com.br', 'Ana Almoxarife');
        $financeiro = $this->usuario($tenant, 'financeiro@comendador.com.br', 'Paulo Financeiro');

        $this->papel($hellen, 'compras', 'Compras');
        $this->papel($financeiro, 'financeiro', 'Financeiro');

        TenantContext::runFor($tenant->id, function () use ($tenant, $admin, $joao, $diretor, $solicitante, $almoxarife) {
            $obra = Unidade::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'nome' => 'Obra Expansão Norte'],
                [
                    'tipo' => TipoUnidade::Obra->value,
                    'cnpj' => '12345678000195',
                    'endereco' => 'Rodovia BR-101, km 45, Norte',
                    'gestor_id' => $joao->id,
                    'status' => 'ativa',
                ],
            );

            Obra::withoutGlobalScopes()->updateOrCreate(
                ['unidade_id' => $obra->id],
                [
                    'iniciada_em' => '2024-01-15',
                    'previsao_termino' => '2026-12-31',
                    'status' => 'ativa',
                    'verba' => 2500000.00,
                ],
            );

            $posto = Unidade::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'nome' => 'Posto Comendador Centro'],
                [
                    'tipo' => TipoUnidade::Posto->value,
                    'cnpj' => '98765432000110',
                    'endereco' => 'Av. Central, 1000, Centro',
                    'gestor_id' => $joao->id,
                    'status' => 'ativa',
                ],
            );

            Unidade::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'nome' => 'Central Administrativa'],
                [
                    'tipo' => TipoUnidade::Central->value,
                    'cnpj' => null,
                    'endereco' => 'Rua das Empresas, 500, Bairro Empresarial',
                    'gestor_id' => null,
                    'status' => 'ativa',
                ],
            );

            $obra->usuarios()->syncWithoutDetaching([
                $joao->id => ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value],
                $diretor->id => ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Diretor->value],
                $solicitante->id => ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null],
            ]);
            $posto->usuarios()->syncWithoutDetaching([
                $diretor->id => ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Diretor->value],
                $almoxarife->id => ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null],
            ]);

            $this->alcadas();
            $this->fornecedores($admin);

            $this->call([
                CatalogoItemSeeder::class,
                BancoSeeder::class,
                CentroCustoSeeder::class,
            ]);
        });

        $this->command->info('Cenário Empresa A pronto. Senha de todos: '.self::SENHA);
        $this->command->table(['Usuário', 'Papel no Compras'], [
            ['hellen@comendador.com.br', 'Compradora sênior (role compras)'],
            ['solicitante@comendador.com.br', 'Solicitante — Obra Expansão Norte'],
            ['joao.rh@comendador.com.br', 'Aprovador nível Gestor — Obra'],
            ['diretor@comendador.com.br', 'Aprovador nível Diretor — Obra + Posto'],
            ['almoxarife@comendador.com.br', 'Almoxarife — Posto'],
            ['financeiro@comendador.com.br', 'Financeiro (role financeiro)'],
            ['admin.a@comendador.com.br', 'Admin do tenant'],
        ]);
    }

    private function usuario(Tenant $tenant, string $email, string $nome, bool $isAdmin = false): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nome,
                'password' => Hash::make(self::SENHA),
                'tenant_id' => $tenant->id,
                'is_admin' => $isAdmin,
                'status' => 'active',
                'precisa_trocar_senha' => false,
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->saveQuietly();

        $user->memberships()->syncWithoutDetaching([
            $tenant->id => ['is_admin' => $isAdmin, 'status' => 'active'],
        ]);

        return $user;
    }

    private function papel(User $user, string $slug, string $nome): void
    {
        $tenantId = $user->getAttributes()['tenant_id'];

        $role = Role::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => $slug],
            ['name' => $nome],
        );

        $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenantId]]);
    }

    private function alcadas(): void
    {
        if (FaixaAlcada::query()->exists()) {
            return;
        }

        $faixas = [
            ['Pequenas Compras (até R$5.000)', 0.00, 5000.00, false, [NivelAlcada::Gestor]],
            ['Compras Médias (R$5.001 a R$20.000)', 5000.01, 20000.00, false, [NivelAlcada::Diretor]],
            ['Grandes Compras (acima R$20.000)', 20000.01, null, false, [NivelAlcada::Diretor, NivelAlcada::Ceo]],
            ['Emergencial', 0.00, null, true, [NivelAlcada::Diretor]],
        ];

        foreach ($faixas as [$nome, $min, $max, $emergencial, $niveis]) {
            $faixa = FaixaAlcada::create([
                'nome' => $nome,
                'valor_minimo' => $min,
                'valor_maximo' => $max,
                'is_emergencial' => $emergencial,
                'ativo' => true,
            ]);

            foreach ($niveis as $ordem => $nivel) {
                EtapaAlcada::create([
                    'faixa_alcada_id' => $faixa->id,
                    'ordem' => $ordem + 1,
                    'nivel_exigido' => $nivel->value,
                ]);
            }
        }
    }

    private function fornecedores(User $admin): void
    {
        $lista = [
            ['Distribuidora Norte Ltda', 'DistriNorte', '11222333000181', 'materiais', 'Carlos Silva', 'carlos@distrinorte.com.br', '(11) 98765-4321', true],
            ['Equipamentos Industriais do Brasil S.A.', 'EIB', '44555666000177', 'equipamentos', 'Ana Souza', 'ana@eib.com.br', '(21) 3333-4444', true],
            ['Serviços Gerais Omega ME', null, '77888999000165', 'servicos', 'João Mendes', 'joao@omega.com.br', '(31) 99999-0000', false],
        ];

        foreach ($lista as [$razao, $fantasia, $cnpj, $categoria, $contato, $email, $fone, $homologado]) {
            Fornecedor::firstOrCreate(['cnpj' => $cnpj], [
                'razao_social' => $razao,
                'nome_fantasia' => $fantasia,
                'categoria' => $categoria,
                'contato_nome' => $contato,
                'contato_email' => $email,
                'contato_telefone' => $fone,
                'homologado' => $homologado,
                'homologado_em' => $homologado ? now()->subDays(30) : null,
                'homologado_por' => $homologado ? $admin->id : null,
                'ativo' => true,
            ]);
        }
    }
}
