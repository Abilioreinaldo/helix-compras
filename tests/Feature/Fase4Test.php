<?php

use App\Actions\AprovarEtapaAction;
use App\Actions\IniciarAprovacaoAction;
use App\Actions\ReprovarRequisicaoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ───────────────────────────────────────────────────────────────

/**
 * Cria uma requisição em CotacaoConcluida pronta para iniciar aprovação.
 *
 * @return array{requisicao: Requisicao, unidade: Unidade, solicitante: User, faixa: FaixaAlcada, aprovadorGestor: User}
 */
function setupRequisicaoParaAprovacao(bool $emergencial = false): array
{
    $unidade = Unidade::factory()->create();

    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'valor_maximo' => null,
        'is_emergencial' => $emergencial,
        'ativo' => true,
        'minimo_cotacoes' => 1,
    ]);

    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value,
    ]);

    $aprovadorGestor = User::factory()->create();
    $aprovadorGestor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Gestor->value,
    ]);

    if ($emergencial) {
        $aprovadorDiretor = User::factory()->create();
        $aprovadorDiretor->unidades()->attach($unidade->id, [
            'perfil' => Perfil::Aprovador->value,
            'nivel_alcada' => NivelAlcada::Diretor->value,
        ]);
    }

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => $emergencial,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now()->subHour(),
        'triagem_iniciada_em' => now()->subMinutes(50),
        'cotacao_concluida_em' => now()->subMinutes(5),
        'ciclo_aprovacao' => 1,
    ]);

    return compact('requisicao', 'unidade', 'solicitante', 'faixa', 'aprovadorGestor');
}

// ─── IniciarAprovacaoAction ────────────────────────────────────────────────

it('iniciar_aprovacao_materializa_etapas_e_transita_para_aguardando', function () {
    ['requisicao' => $req] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);

    expect($req->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and($req->fresh()->aprovacao_iniciada_em)->not->toBeNull();

    $aprovacoes = Aprovacao::where('requisicao_id', $req->id)->get();
    expect($aprovacoes)->toHaveCount(1);

    $aprovacao = $aprovacoes->first();
    expect($aprovacao->ciclo)->toBe(1)
        ->and($aprovacao->ordem)->toBe(1)
        ->and($aprovacao->nivel_exigido)->toBe(NivelAlcada::Gestor)
        ->and($aprovacao->status)->toBe(StatusAprovacao::Pendente);
});

it('iniciar_aprovacao_emergencial_prepend_diretor', function () {
    ['requisicao' => $req] = setupRequisicaoParaAprovacao(emergencial: true);

    app(IniciarAprovacaoAction::class)->execute($req);

    $aprovacoes = Aprovacao::where('requisicao_id', $req->id)->orderBy('ordem')->get();

    expect($aprovacoes)->toHaveCount(2);
    expect($aprovacoes[0]->nivel_exigido)->toBe(NivelAlcada::Diretor)
        ->and($aprovacoes[0]->obrigatoria_emergencial)->toBeTrue()
        ->and($aprovacoes[0]->ordem)->toBe(0);
    expect($aprovacoes[1]->nivel_exigido)->toBe(NivelAlcada::Gestor)
        ->and($aprovacoes[1]->ordem)->toBe(1);
});

it('iniciar_aprovacao_sem_aprovadores_lanca_excecao', function () {
    ['requisicao' => $req, 'unidade' => $unidade, 'aprovadorGestor' => $aprovadorGestor] = setupRequisicaoParaAprovacao();

    $aprovadorGestor->unidades()->detach($unidade->id);

    expect(fn () => app(IniciarAprovacaoAction::class)->execute($req))
        ->toThrow(ValidationException::class);
});

it('iniciar_aprovacao_faixa_sem_etapas_lanca_excecao', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'ativo' => true, 'minimo_cotacoes' => 1]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000099',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'cotacao_concluida_em' => now(),
        'ciclo_aprovacao' => 1,
    ]);

    expect(fn () => app(IniciarAprovacaoAction::class)->execute($requisicao))
        ->toThrow(ValidationException::class);
});

// ─── AprovarEtapaAction ────────────────────────────────────────────────────

it('aprovar_etapa_unica_conclui_aprovacao', function () {
    ['requisicao' => $req, 'aprovadorGestor' => $aprovador] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);

    $this->actingAs($aprovador);
    app(AprovarEtapaAction::class)->execute($req, $aprovador, 'Aprovado conforme análise.');

    $req->refresh();
    expect($req->status)->toBe(StatusRequisicao::Aprovada)
        ->and($req->aprovada_em)->not->toBeNull();

    $aprovacao = Aprovacao::where('requisicao_id', $req->id)->first();
    expect($aprovacao->status)->toBe(StatusAprovacao::Aprovada)
        ->and($aprovacao->aprovador_id)->toBe($aprovador->id)
        ->and($aprovacao->justificativa)->toBe('Aprovado conforme análise.')
        ->and($aprovacao->decidida_em)->not->toBeNull();
});

it('aprovar_etapa_multietapa_avanca_para_proxima', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'ativo' => true, 'minimo_cotacoes' => 1]);
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value,
    ]);
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 2,
        'nivel_exigido' => NivelAlcada::Diretor->value,
    ]);

    $aprovadorGestor = User::factory()->create();
    $aprovadorGestor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Gestor->value,
    ]);
    $aprovadorDiretor = User::factory()->create();
    $aprovadorDiretor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Diretor->value,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000010',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'cotacao_concluida_em' => now(),
        'ciclo_aprovacao' => 1,
    ]);

    app(IniciarAprovacaoAction::class)->execute($requisicao);

    app(AprovarEtapaAction::class)->execute($requisicao, $aprovadorGestor, '');
    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao);

    app(AprovarEtapaAction::class)->execute($requisicao, $aprovadorDiretor, '');
    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::Aprovada);
});

it('aprovar_etapa_sem_permissao_lanca_excecao', function () {
    ['requisicao' => $req, 'unidade' => $unidade] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);

    $semPermissao = User::factory()->create();
    $semPermissao->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Diretor->value,
    ]);

    expect(fn () => app(AprovarEtapaAction::class)->execute($req, $semPermissao, ''))
        ->toThrow(ValidationException::class);
});

it('solicitante_nao_pode_aprovar_propria_requisicao', function () {
    ['requisicao' => $req, 'solicitante' => $solicitante, 'unidade' => $unidade] = setupRequisicaoParaAprovacao();

    $solicitante->unidades()->syncWithPivotValues(
        [$unidade->id],
        ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]
    );

    app(IniciarAprovacaoAction::class)->execute($req);

    expect(fn () => app(AprovarEtapaAction::class)->execute($req, $solicitante, ''))
        ->toThrow(ValidationException::class);
});

// ─── ReprovarRequisicaoAction ──────────────────────────────────────────────

it('reprovar_requisicao_retorna_para_cotacao', function () {
    ['requisicao' => $req, 'aprovadorGestor' => $aprovador] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);

    app(ReprovarRequisicaoAction::class)->execute($req, $aprovador, 'Preço fora do mercado.');

    $req->refresh();
    expect($req->status)->toBe(StatusRequisicao::EmCotacao)
        ->and($req->reprovada_em)->not->toBeNull()
        ->and($req->reprovada_por)->toBe($aprovador->id);
});

it('reprovar_incrementa_ciclo_aprovacao', function () {
    ['requisicao' => $req, 'aprovadorGestor' => $aprovador] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);
    expect($req->fresh()->ciclo_aprovacao)->toBe(1);

    app(ReprovarRequisicaoAction::class)->execute($req, $aprovador, 'Justificativa suficiente.');

    expect($req->fresh()->ciclo_aprovacao)->toBe(2);
});

it('reprovar_marca_etapas_pendentes_como_puladas', function () {
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'ativo' => true, 'minimo_cotacoes' => 1]);
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value,
    ]);
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 2,
        'nivel_exigido' => NivelAlcada::Diretor->value,
    ]);

    $aprovadorGestor = User::factory()->create();
    $aprovadorGestor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Gestor->value,
    ]);
    $aprovadorDiretor = User::factory()->create();
    $aprovadorDiretor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Diretor->value,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000020',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'cotacao_concluida_em' => now(),
        'ciclo_aprovacao' => 1,
    ]);

    app(IniciarAprovacaoAction::class)->execute($requisicao);

    app(ReprovarRequisicaoAction::class)->execute($requisicao, $aprovadorGestor, 'Documentação incompleta.');

    $aprovacoes = Aprovacao::where('requisicao_id', $requisicao->id)
        ->where('ciclo', 1)
        ->orderBy('ordem')
        ->get();

    expect($aprovacoes[0]->status)->toBe(StatusAprovacao::Reprovada);
    expect($aprovacoes[1]->status)->toBe(StatusAprovacao::Pulada);
});

it('solicitante_nao_pode_reprovar_propria_requisicao', function () {
    ['requisicao' => $req, 'solicitante' => $solicitante, 'unidade' => $unidade] = setupRequisicaoParaAprovacao();

    $solicitante->unidades()->syncWithPivotValues(
        [$unidade->id],
        ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]
    );

    app(IniciarAprovacaoAction::class)->execute($req);

    expect(fn () => app(ReprovarRequisicaoAction::class)->execute($req, $solicitante, 'Justificativa.'))
        ->toThrow(ValidationException::class);
});

it('reprovar_sem_permissao_lanca_excecao', function () {
    ['requisicao' => $req, 'unidade' => $unidade] = setupRequisicaoParaAprovacao();

    app(IniciarAprovacaoAction::class)->execute($req);

    $semPermissao = User::factory()->create();
    $semPermissao->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Ceo->value,
    ]);

    expect(fn () => app(ReprovarRequisicaoAction::class)->execute($req, $semPermissao, 'Sem permissão.'))
        ->toThrow(ValidationException::class);
});
