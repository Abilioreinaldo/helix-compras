<?php

use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function tc_requisicao(StatusRequisicao $status): Requisicao
{
    $unidade = Unidade::factory()->create();
    $user = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    return Requisicao::create([
        'solicitante_id' => $user->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => $status,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-TEST-'.fake()->unique()->numerify('####'),
        'submetida_em' => now()->subHour(),
        'ciclo_aprovacao' => 1,
    ]);
}

// ─── Transições para Concluida ───────────────────────────────────────────────

it('transicao_aguardando_triagem_para_concluida_e_permitida', function () {
    $requisicao = tc_requisicao(StatusRequisicao::AguardandoTriagem);

    app(TransicionarStatusRequisicaoAction::class)->execute(
        $requisicao,
        StatusRequisicao::Concluida,
        'Atendido diretamente do estoque.',
    );

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::Concluida);
});

it('transicao_em_triagem_para_concluida_e_permitida', function () {
    $requisicao = tc_requisicao(StatusRequisicao::EmTriagem);

    app(TransicionarStatusRequisicaoAction::class)->execute(
        $requisicao,
        StatusRequisicao::Concluida,
        'Atendido diretamente do estoque na triagem.',
    );

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::Concluida);
});

it('transicao_rascunho_para_concluida_e_barrada', function () {
    $requisicao = tc_requisicao(StatusRequisicao::Rascunho);

    expect(fn () => app(TransicionarStatusRequisicaoAction::class)->execute(
        $requisicao,
        StatusRequisicao::Concluida,
    ))->toThrow(ValidationException::class);

    $requisicao->refresh();
    expect($requisicao->status)->toBe(StatusRequisicao::Rascunho);
});

it('transicao_em_cotacao_para_concluida_nao_e_permitida', function () {
    $requisicao = tc_requisicao(StatusRequisicao::EmCotacao);

    expect(fn () => app(TransicionarStatusRequisicaoAction::class)->execute(
        $requisicao,
        StatusRequisicao::Concluida,
    ))->toThrow(ValidationException::class);
});
