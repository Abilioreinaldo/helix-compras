<?php

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Aprovacoes\FilaAprovacoes;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Aprovador (com par unidade×nível) + requisição aguardando aprovação com etapa pendente.
 *
 * @return array{unidade: Unidade, aprovador: User, req: Requisicao}
 */
function fa_cenario(string $codigo, string $nivel = 'gestor'): array
{
    $unidade = Unidade::factory()->create();
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => $nivel]);

    $solicitante = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $req = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'codigo' => $codigo,
        'urgente' => false,
        'is_emergencial' => false,
        'ciclo_aprovacao' => 1,
        'submetida_em' => now(),
        'aprovacao_iniciada_em' => now(),
    ]);

    Aprovacao::create([
        'requisicao_id' => $req->id,
        'ciclo' => 1,
        'ordem' => 1,
        'nivel_exigido' => $nivel,
        'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    return compact('unidade', 'aprovador', 'req');
}

it('lista as requisições pendentes do aprovador', function () {
    $c = fa_cenario('REQ-FA-0001');

    Livewire::actingAs($c['aprovador'])
        ->test(FilaAprovacoes::class)
        ->assertOk()
        ->assertSee('REQ-FA-0001');
});

it('filtra a fila por unidade', function () {
    $c = fa_cenario('REQ-FA-UNIT');

    Livewire::actingAs($c['aprovador'])
        ->test(FilaAprovacoes::class)
        ->assertSee('REQ-FA-UNIT')
        ->set('filtroUnidadeId', (string) ($c['unidade']->id + 999))
        ->assertDontSee('REQ-FA-UNIT')
        ->set('filtroUnidadeId', (string) $c['unidade']->id)
        ->assertSee('REQ-FA-UNIT');
});

it('bloqueia quem não é aprovador (403)', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('aprovacoes.fila'))->assertForbidden();
});
