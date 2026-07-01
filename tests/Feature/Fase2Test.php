<?php

use App\Actions\SubmeterRequisicaoAction;
use App\Actions\TransicionarStatusRequisicaoAction;
use App\Console\Commands\MarcarRequisicoesAtrasadas;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Obra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// Helpers
function criarSolicitante(?Unidade $unidade = null): User
{
    $u = $unidade ?? Unidade::factory()->create();
    $user = User::factory()->create();
    $user->unidades()->attach($u->id, ['perfil' => Perfil::Solicitante->value]);

    return $user;
}

function criarFaixaPadrao(float $min = 0, ?float $max = null): FaixaAlcada
{
    return FaixaAlcada::factory()->create([
        'valor_minimo' => $min,
        'valor_maximo' => $max,
        'is_emergencial' => false,
        'ativo' => true,
    ]);
}

function criarRequisicaoComItem(User $user, Unidade $unidade, ?Obra $obra = null, float $valorUnit = 100.0): Requisicao
{
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $req = Requisicao::create([
        'solicitante_id' => $user->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'obra_id' => $obra?->id,
        'status' => StatusRequisicao::Rascunho,
        'urgente' => false,
        'is_emergencial' => false,
    ]);
    $req->itens()->create([
        'descricao' => 'Item de teste',
        'quantidade' => 1,
        'unidade_medida' => 'un',
        'valor_unitario_estimado' => $valorUnit,
    ]);

    return $req;
}

// ─────────────────────────────────────────────────────────────────────────────

it('solicitante_pode_criar_requisicao_como_rascunho', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $req = Requisicao::create([
        'solicitante_id' => $user->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Rascunho,
        'urgente' => false,
        'is_emergencial' => false,
    ]);

    expect($req->status)->toBe(StatusRequisicao::Rascunho)
        ->and($req->codigo)->toBeNull();
});

it('solicitante_pode_submeter_requisicao_sem_obra', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade, valorUnit: 50.0);

    $this->actingAs($user);
    app(SubmeterRequisicaoAction::class)->execute($req);

    $req->refresh();
    expect($req->status)->toBe(StatusRequisicao::AguardandoTriagem)
        ->and($req->codigo)->not->toBeNull()
        ->and($req->faixa_alcada_id)->not->toBeNull();
});

it('requisicao_submetida_fica_aguardando_triagem', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade);

    $this->actingAs($user);
    app(SubmeterRequisicaoAction::class)->execute($req);

    expect($req->fresh()->status)->toBe(StatusRequisicao::AguardandoTriagem);
});

it('log_de_status_gerado_na_submissao', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade);

    $this->actingAs($user);
    app(SubmeterRequisicaoAction::class)->execute($req);

    $log = $req->logs()->first();
    expect($log)->not->toBeNull()
        ->and($log->status_anterior)->toBe(StatusRequisicao::Rascunho)
        ->and($log->status_novo)->toBe(StatusRequisicao::AguardandoTriagem);
});

it('sem_faixa_alcada_bloqueia_submissao', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);

    $req = criarRequisicaoComItem($user, $unidade, valorUnit: 500.0);

    $this->actingAs($user);

    expect(fn () => app(SubmeterRequisicaoAction::class)->execute($req))
        ->toThrow(ValidationException::class);
});

it('verba_abaixo_80_nao_gera_alerta', function () {
    $unidade = Unidade::factory()->create();
    $obra = Obra::factory()->create(['unidade_id' => $unidade->id, 'verba' => 10000]);
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade, $obra, valorUnit: 500.0); // 5% da verba

    $this->actingAs($user);
    $resultado = app(SubmeterRequisicaoAction::class)->execute($req);

    expect($resultado['alerta_verba'])->toBeFalse()
        ->and($req->fresh()->escalada_verba)->toBeFalse();
});

it('verba_entre_80_e_100_gera_escalada_verba', function () {
    $unidade = Unidade::factory()->create();
    $obra = Obra::factory()->create(['unidade_id' => $unidade->id, 'verba' => 10000]);
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    // Cria requisição aprovada que consome 70%
    $outraReq = criarRequisicaoComItem($user, $unidade, $obra, valorUnit: 7000.0);
    $outraReq->update(['status' => StatusRequisicao::Aprovada]);

    // Nova requisição que leva para 85%
    $req = criarRequisicaoComItem($user, $unidade, $obra, valorUnit: 1500.0);

    $this->actingAs($user);
    $resultado = app(SubmeterRequisicaoAction::class)->execute($req);

    expect($resultado['alerta_verba'])->toBeTrue()
        ->and($req->fresh()->escalada_verba)->toBeTrue();
});

it('verba_acima_100_bloqueia_submissao', function () {
    $unidade = Unidade::factory()->create();
    $obra = Obra::factory()->create(['unidade_id' => $unidade->id, 'verba' => 1000]);
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    // Outra requisição que já consome 90%
    $outraReq = criarRequisicaoComItem($user, $unidade, $obra, valorUnit: 900.0);
    $outraReq->update(['status' => StatusRequisicao::AguardandoAprovacao]);

    // Nova requisição que estoura
    $req = criarRequisicaoComItem($user, $unidade, $obra, valorUnit: 200.0);

    $this->actingAs($user);

    expect(fn () => app(SubmeterRequisicaoAction::class)->execute($req))
        ->toThrow(ValidationException::class);
});

it('solicitante_nao_ve_requisicao_de_outra_unidade', function () {
    $unidadeA = Unidade::factory()->create();
    $unidadeB = Unidade::factory()->create();

    $userA = criarSolicitante($unidadeA);
    $userB = criarSolicitante($unidadeB);

    criarFaixaPadrao(0);

    $reqA = criarRequisicaoComItem($userA, $unidadeA);
    $this->actingAs($userA);
    app(SubmeterRequisicaoAction::class)->execute($reqA);

    $reqB = criarRequisicaoComItem($userB, $unidadeB);
    $this->actingAs($userB);
    app(SubmeterRequisicaoAction::class)->execute($reqB);

    // userA não deve ver requisição de unidadeB
    $this->actingAs($userA);
    $visiveis = Requisicao::all()->pluck('unidade_id')->unique();

    expect($visiveis)->not->toContain($unidadeB->id);
});

it('compradora_ve_todas_as_requisicoes', function () {
    $unidadeA = Unidade::factory()->create();
    $unidadeB = Unidade::factory()->create();

    $userA = criarSolicitante($unidadeA);
    $userB = criarSolicitante($unidadeB);
    criarFaixaPadrao(0);

    $reqA = criarRequisicaoComItem($userA, $unidadeA);
    $this->actingAs($userA);
    app(SubmeterRequisicaoAction::class)->execute($reqA);

    $reqB = criarRequisicaoComItem($userB, $unidadeB);
    $this->actingAs($userB);
    app(SubmeterRequisicaoAction::class)->execute($reqB);

    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $visiveis = Requisicao::withoutGlobalScopes()->count();

    expect($visiveis)->toBe(2);
});

it('command_marca_atrasada_apos_24h', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade);
    $this->actingAs($user);
    app(SubmeterRequisicaoAction::class)->execute($req);

    // Simular que foi submetida há 25h
    $req->fresh()->update(['submetida_em' => now()->subHours(25)]);

    $this->artisan(MarcarRequisicoesAtrasadas::class)->assertSuccessful();

    expect($req->fresh()->atrasada)->toBeTrue();
});

it('command_nao_marca_atrasada_se_menos_de_24h', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);
    criarFaixaPadrao(0);

    $req = criarRequisicaoComItem($user, $unidade);
    $this->actingAs($user);
    app(SubmeterRequisicaoAction::class)->execute($req);
    // submetida_em foi setada para now() — menos de 24h

    $this->artisan(MarcarRequisicoesAtrasadas::class)->assertSuccessful();

    expect($req->fresh()->atrasada)->toBeFalse();
});

it('transicao_invalida_lanca_excecao', function () {
    $unidade = Unidade::factory()->create();
    $user = criarSolicitante($unidade);

    $req = criarRequisicaoComItem($user, $unidade);

    expect(fn () => app(TransicionarStatusRequisicaoAction::class)->execute($req, StatusRequisicao::Aprovada))
        ->toThrow(ValidationException::class);
});
