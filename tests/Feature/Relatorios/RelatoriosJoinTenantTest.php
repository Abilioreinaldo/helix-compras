<?php

use App\Enums\NivelAlcada;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Relatorios\CustoObra;
use App\Livewire\Relatorios\RequisicoesAprovador;
use App\Livewire\Relatorios\TempoAprovacao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\ItemPedidoCompra;
use App\Models\Obra;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Models\Platform\Identity\TenantFeature;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Tenant nos JOINS dos relatórios (auditoria adversarial, achado BAIXO).
 *
 * Os relatórios montam query builder puro — que NÃO passa pelo BelongsToTenant.
 * Três deles filtravam o tenant só na tabela principal e juntavam `faixas_alcada`,
 * `unidades` e `users` sem recorte: bastava uma FK apontando para fora (dado
 * plantado, resíduo de migração, vínculo revogado) para o nome do outro tenant
 * aparecer no relatório. Os testes abaixo plantam exatamente isso.
 */
beforeEach(function () {
    Mail::fake();

    $this->tenantA = Tenant::create(['slug' => 'rel-alfa', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'rel-bravo', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        TenantFeature::firstOrCreate(['tenant_id' => $tenant->id, 'feature' => 'compras'], ['enabled' => true]);
    }

    TenantContext::forget();

    $this->compradoraA = TenantContext::runFor(
        $this->tenantA->id,
        fn () => User::factory()->compradora()->create(['tenant_id' => $this->tenantA->id])
    );
});

it('não traz o nome da faixa de alçada de outro tenant no tempo de aprovação', function () {
    $faixaB = TenantContext::runFor($this->tenantB->id, fn () => FaixaAlcada::factory()->create([
        'nome' => 'Faixa Secreta Bravo', 'valor_minimo' => 0,
    ]));

    $reqA = TenantContext::runFor($this->tenantA->id, fn () => Requisicao::factory()->create([
        'status' => StatusRequisicao::Aprovada,
        'codigo' => 'REQ-ALFA-000101',
        'submetida_em' => now()->subHours(3),
        'aprovacao_iniciada_em' => now()->subHours(2),
        'aprovada_em' => now(),
        'ciclo_aprovacao' => 1,
    ]));

    // FK plantada: requisição do tenant A apontando para a faixa do tenant B.
    DB::table('requisicoes')->where('id', $reqA->id)->update(['faixa_alcada_id' => $faixaB->id]);

    $resultados = Livewire::actingAs($this->compradoraA)
        ->test(TempoAprovacao::class)
        ->viewData('resultados');

    expect($resultados->pluck('faixa_nome')->all())->not->toContain('Faixa Secreta Bravo')
        ->and($resultados->pluck('faixa_nome')->all())->toContain('Sem faixa');
});

it('não traz o nome da unidade de outro tenant no custo por obra', function () {
    $unidadeB = TenantContext::runFor($this->tenantB->id, fn () => Unidade::factory()->obra()->create([
        'nome' => 'Obra Secreta Bravo',
    ]));

    $dados = TenantContext::runFor($this->tenantA->id, function () {
        $unidadeA = Unidade::factory()->obra()->create(['nome' => 'Obra Alfa']);
        $obraA = Obra::factory()->create(['unidade_id' => $unidadeA->id]);
        $centroA = CentroCusto::factory()->create(['unidade_id' => $unidadeA->id, 'ativo' => true]);

        $req = Requisicao::factory()->create([
            'unidade_id' => $unidadeA->id,
            'centro_custo_id' => $centroA->id,
            'obra_id' => $obraA->id,
            'status' => StatusRequisicao::Aprovada,
            'codigo' => 'REQ-ALFA-000202',
        ]);

        $pedido = PedidoCompra::factory()->emitido()->create([
            'unidade_id' => $unidadeA->id,
            'emitido_em' => now(),
        ]);

        ItemPedidoCompra::factory()->create([
            'pedido_compra_id' => $pedido->id,
            'requisicao_id' => $req->id,
            'valor_total' => 1000,
        ]);

        return ['obra' => $obraA];
    });

    // FK plantada: a obra do tenant A passa a apontar para a unidade do tenant B.
    DB::table('obras')->where('id', $dados['obra']->id)->update(['unidade_id' => $unidadeB->id]);

    $curvas = Livewire::actingAs($this->compradoraA)
        ->test(CustoObra::class)
        ->viewData('curvas');

    expect($curvas->pluck('obra_nome')->all())->not->toContain('Obra Secreta Bravo');
});

it('não traz o nome de aprovador que não é membro deste tenant', function () {
    // Identidade de outro tenant (sem membership aqui) deixada como aprovadora.
    $forasteiro = TenantContext::runFor($this->tenantB->id, fn () => User::factory()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Forasteiro Bravo',
    ]));

    TenantContext::runFor($this->tenantA->id, function () use ($forasteiro) {
        $req = Requisicao::factory()->create([
            'status' => StatusRequisicao::AguardandoAprovacao,
            'codigo' => 'REQ-ALFA-000303',
            'ciclo_aprovacao' => 1,
            'submetida_em' => now()->subDay(),
        ]);

        Aprovacao::create([
            'requisicao_id' => $req->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente->value, 'aprovador_id' => $forasteiro->id,
        ]);
    });

    $resultados = Livewire::actingAs($this->compradoraA)
        ->test(RequisicoesAprovador::class)
        ->viewData('resultados');

    expect($resultados->pluck('aprovador_nome')->all())->not->toContain('Forasteiro Bravo');
});
