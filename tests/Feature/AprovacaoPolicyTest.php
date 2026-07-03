<?php

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| AprovacaoPolicy — Gates nomeados aprovacao.* (fila / painel / decidir).
|--------------------------------------------------------------------------
|
| Cobre a camada extraída nos cenários pedidos: aprovador da unidade+nível corretos,
| aprovador de outra unidade, aprovador de nível errado, compradora/admin (regra atual)
| e usuário comum. A regra não mudou — espelha os checks de FilaAprovacoes/PainelAprovacao.
| A decisão efetiva (nível-por-etapa) segue validada nas Actions.
|
*/

uses(RefreshDatabase::class);

function ap_requisicaoComEtapa(Unidade $unidade, NivelAlcada $nivelEtapa): Requisicao
{
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true]);

    $requisicao = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-AP-'.fake()->unique()->numerify('######'),
    ]);
    Aprovacao::create([
        'requisicao_id' => $requisicao->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
        'nivel_exigido' => $nivelEtapa->value, 'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    return $requisicao;
}

function ap_aprovador(Unidade $unidade, NivelAlcada $nivel): User
{
    $user = User::factory()->create();
    $user->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => $nivel->value]);

    return $user;
}

it('aprovador da unidade e nível corretos acessa o painel e decide a etapa', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = ap_requisicaoComEtapa($unidade, NivelAlcada::Gestor);
    $aprovador = ap_aprovador($unidade, NivelAlcada::Gestor);

    expect($aprovador->can('aprovacao.acessar', $requisicao))->toBeTrue()
        ->and($aprovador->can('aprovacao.decidir', $requisicao))->toBeTrue();
});

it('aprovador de outra unidade NÃO acessa nem decide', function () {
    $requisicao = ap_requisicaoComEtapa(Unidade::factory()->create(), NivelAlcada::Gestor);
    $deOutraUnidade = ap_aprovador(Unidade::factory()->create(), NivelAlcada::Gestor);

    expect($deOutraUnidade->can('aprovacao.acessar', $requisicao))->toBeFalse()
        ->and($deOutraUnidade->can('aprovacao.decidir', $requisicao))->toBeFalse();
});

it('aprovador de nível errado acessa o painel mas NÃO decide a etapa', function () {
    $unidade = Unidade::factory()->create();
    $requisicao = ap_requisicaoComEtapa($unidade, NivelAlcada::Diretor); // etapa exige Diretor
    $gestor = ap_aprovador($unidade, NivelAlcada::Gestor);               // nível insuficiente

    expect($gestor->can('aprovacao.acessar', $requisicao))->toBeTrue()
        ->and($gestor->can('aprovacao.decidir', $requisicao))->toBeFalse();
});

it('compradora sênior não é aprovadora por padrão (não acessa o painel)', function () {
    $requisicao = ap_requisicaoComEtapa(Unidade::factory()->create(), NivelAlcada::Gestor);

    expect(User::factory()->compradora()->create()->can('aprovacao.acessar', $requisicao))->toBeFalse();
});

it('admin do tenant passa pelos gates de aprovação (Gate::before da fundação: "admin passa por tudo")', function () {
    // Decisão existente da plataforma (helix-foundation FoundationServiceProvider): superadmin/
    // admin-do-tenant são liberados em QUALQUER ability via Gate::before. Ao migrar para
    // authorize()/can(), o admin passa a acessar — diferente do antigo check manual temPerfil.
    $requisicao = ap_requisicaoComEtapa(Unidade::factory()->create(), NivelAlcada::Gestor);

    expect(User::factory()->admin()->create()->can('aprovacao.acessar', $requisicao))->toBeTrue();
});

it('usuário comum não acessa a fila nem o painel', function () {
    $requisicao = ap_requisicaoComEtapa(Unidade::factory()->create(), NivelAlcada::Gestor);
    $comum = User::factory()->create();

    expect($comum->can('aprovacao.acessar-fila'))->toBeFalse()
        ->and($comum->can('aprovacao.acessar', $requisicao))->toBeFalse();
});

it('aprovador acessa a fila de aprovações', function () {
    $aprovador = ap_aprovador(Unidade::factory()->create(), NivelAlcada::Gestor);

    expect($aprovador->can('aprovacao.acessar-fila'))->toBeTrue();
});
