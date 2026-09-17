<?php

use App\Enums\StatusRequisicao;
use App\Livewire\Compradora\GestaoCotacoes;
use App\Livewire\Compradora\MapaCotacao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Livewire\Component;
use Livewire\Features\SupportModels\ModelSynth;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleSynths\HandleSynths;

/**
 * v0.4.0 — reidratação de Model no Livewire do Compras.
 *
 * 1. O synth GLOBAL da fundação (TenantAwareModelSynth) está ativo no Livewire 4.4
 *    deste app — o kit da fundação não consegue detectá-lo nesta versão (ver
 *    HelixConformanceTest). A sonda não tem hydrate()/trait: só o synth barra.
 * 2. GestaoCotacoes e MapaCotacao autorizam `operar` a CADA reidratação
 *    (AuthorizesOnHydrate), independente do synth: com o ModelSynth original do
 *    Livewire no lugar, o snapshot reaproveitado noutro tenant continua 403.
 */
class SondaReidratacaoCompras extends Component
{
    public Requisicao $requisicao;

    public string $campo = '';

    public function render(): string
    {
        return '<div>{{ $requisicao->codigo }}</div>';
    }
}

function hg_requisicaoEmCotacao(string $codigo): Requisicao
{
    $unidade = Unidade::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['minimo_cotacoes' => 3, 'is_emergencial' => false, 'ativo' => true]);

    $req = Requisicao::create([
        'solicitante_id' => User::factory()->create()->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'codigo' => $codigo,
        'urgente' => false,
        'is_emergencial' => false,
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
    ]);
    $req->itens()->create(['descricao' => 'Mouse', 'quantidade' => 5, 'unidade_medida' => 'un', 'valor_unitario_estimado' => 30, 'avulso' => true]);

    return $req;
}

/** Compradora membro ATIVO também do tenant B (a troca de empresa é legítima). */
function hg_compradoraComOutraEmpresa(): array
{
    $compradora = User::factory()->compradora()->create();
    $tenantB = Tenant::create(['slug' => 'hg-b-'.uniqid(), 'name' => 'B', 'status' => 'active']);
    $compradora->memberships()->syncWithoutDetaching([$tenantB->id => ['is_admin' => false, 'status' => 'active', 'access_scope' => 'corporate']]);

    return [$compradora, $tenantB];
}

it('synth da fundação barra snapshot de model reaproveitado noutro tenant ativo', function () {
    [$compradora, $tenantB] = hg_compradoraComOutraEmpresa();
    $req = hg_requisicaoEmCotacao('REQ-SONDA-A');

    $tela = Livewire::actingAs($compradora)
        ->test(SondaReidratacaoCompras::class, ['requisicao' => $req])
        ->assertSee('REQ-SONDA-A');

    TenantContext::set($tenantB->id);

    $tela->set('campo', 'x')->assertForbidden();
});

it('controle: com o ModelSynth original do Livewire a sonda vazaria o registro', function () {
    [$compradora, $tenantB] = hg_compradoraComOutraEmpresa();
    $req = hg_requisicaoEmCotacao('REQ-SONDA-A');

    $tela = Livewire::actingAs($compradora)->test(SondaReidratacaoCompras::class, ['requisicao' => $req]);

    app(HandleSynths::class)->registerSynth(ModelSynth::class);
    TenantContext::set($tenantB->id);

    $tela->set('campo', 'x')->assertOk()->assertSee('REQ-SONDA-A');
});

it('telas de cotação reautorizam `operar` a cada reidratação, mesmo sem o synth da fundação', function (string $componente, string $parametro) {
    [$compradora, $tenantB] = hg_compradoraComOutraEmpresa();
    $req = hg_requisicaoEmCotacao('REQ-COT-A');

    $tela = Livewire::actingAs($compradora)
        ->test($componente, [$parametro => $req->id])
        ->assertOk();

    // Defesa em profundidade: tira a guarda global e troca a empresa ativa.
    app(HandleSynths::class)->registerSynth(ModelSynth::class);
    TenantContext::set($tenantB->id);

    // Round-trip sem action: só re-render.
    $tela->call('$refresh')->assertForbidden();
})->with([
    'gestão de cotações' => [GestaoCotacoes::class, 'id'],
    'mapa de cotação' => [MapaCotacao::class, 'requisicaoId'],
]);
