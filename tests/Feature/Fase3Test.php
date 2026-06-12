<?php

use App\Actions\ConcluirCotacaoAction;
use App\Actions\MarcarCotacaoVencedoraAction;
use App\Actions\RegistrarCotacaoAction;
use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ───────────────────────────────────────────────────────────────

function criarCompradora(): User
{
    return User::factory()->create(['is_compradora' => true]);
}

function criarRequisicaoEmCotacao(?FaixaAlcada $faixa = null): Requisicao
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixaEfetiva = $faixa ?? FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'valor_maximo' => null,
        'is_emergencial' => false,
        'ativo' => true,
        'minimo_cotacoes' => 3,
    ]);

    return Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'obra_id' => null,
        'status' => StatusRequisicao::EmCotacao,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000001',
        'faixa_alcada_id' => $faixaEfetiva->id,
        'submetida_em' => now(),
        'triagem_iniciada_em' => now(),
    ]);
}

function criarFornecedorHomologado(): Fornecedor
{
    return Fornecedor::factory()->homologado()->create();
}

// ─── Testes ────────────────────────────────────────────────────────────────

it('registrar_cotacao_com_fornecedor_homologado', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $fornecedor = criarFornecedorHomologado();

    $this->actingAs($compradora);

    $cotacao = app(RegistrarCotacaoAction::class)->execute(
        $requisicao,
        $fornecedor,
        1500.00
    );

    expect($cotacao)->toBeInstanceOf(Cotacao::class)
        ->and((float) $cotacao->valor)->toBe(1500.00)
        ->and($cotacao->vencedora)->toBeFalse()
        ->and($cotacao->criada_por)->toBe($compradora->id);
});

it('registrar_cotacao_seta_primeira_cotacao_em', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $fornecedor = criarFornecedorHomologado();

    $this->actingAs($compradora);

    expect($requisicao->primeira_cotacao_em)->toBeNull();

    app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 1000.00);

    expect($requisicao->fresh()->primeira_cotacao_em)->not->toBeNull();
});

it('registrar_cotacao_nao_sobrescreve_primeira_cotacao_em', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $f1 = criarFornecedorHomologado();
    $f2 = criarFornecedorHomologado();

    $this->actingAs($compradora);

    app(RegistrarCotacaoAction::class)->execute($requisicao, $f1, 1000.00);
    $primeiraCotacaoEm = $requisicao->fresh()->primeira_cotacao_em;

    app(RegistrarCotacaoAction::class)->execute($requisicao, $f2, 2000.00);

    expect($requisicao->fresh()->primeira_cotacao_em->toIso8601String())
        ->toBe($primeiraCotacaoEm->toIso8601String());
});

it('rejeita_fornecedor_nao_homologado', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->create(['homologado' => false]);

    $this->actingAs($compradora);

    expect(fn () => app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 1000.00))
        ->toThrow(ValidationException::class);
});

it('rejeita_fornecedor_inativo', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $fornecedor = Fornecedor::factory()->homologado()->inativo()->create();

    $this->actingAs($compradora);

    expect(fn () => app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 1000.00))
        ->toThrow(ValidationException::class);
});

it('marcar_cotacao_vencedora_zera_anterior', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $f1 = criarFornecedorHomologado();
    $f2 = criarFornecedorHomologado();

    $this->actingAs($compradora);

    $c1 = app(RegistrarCotacaoAction::class)->execute($requisicao, $f1, 1000.00);
    $c2 = app(RegistrarCotacaoAction::class)->execute($requisicao, $f2, 900.00);

    app(MarcarCotacaoVencedoraAction::class)->execute($requisicao, $c1);
    expect($c1->fresh()->vencedora)->toBeTrue();

    app(MarcarCotacaoVencedoraAction::class)->execute($requisicao, $c2);
    expect($c1->fresh()->vencedora)->toBeFalse()
        ->and($c2->fresh()->vencedora)->toBeTrue();
});

it('concluir_cotacao_abaixo_minimo_lanca_excecao', function () {
    $compradora = criarCompradora();
    $requisicao = criarRequisicaoEmCotacao();
    $fornecedor = criarFornecedorHomologado();

    $this->actingAs($compradora);

    // Só 1 cotação, mínimo é 3
    app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 1000.00);

    expect(fn () => app(ConcluirCotacaoAction::class)->execute($requisicao))
        ->toThrow(ValidationException::class);
});

it('concluir_cotacao_sem_vencedora_lanca_excecao', function () {
    $compradora = criarCompradora();
    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'is_emergencial' => false,
        'ativo' => true,
        'minimo_cotacoes' => 2,
    ]);
    $requisicao = criarRequisicaoEmCotacao($faixa);
    $f1 = criarFornecedorHomologado();
    $f2 = criarFornecedorHomologado();

    $this->actingAs($compradora);

    app(RegistrarCotacaoAction::class)->execute($requisicao, $f1, 1000.00);
    app(RegistrarCotacaoAction::class)->execute($requisicao, $f2, 900.00);

    expect(fn () => app(ConcluirCotacaoAction::class)->execute($requisicao))
        ->toThrow(ValidationException::class);
});

it('concluir_cotacao_avanca_status_para_aguardando_aprovacao', function () {
    Mail::fake();
    $compradora = criarCompradora();
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'is_emergencial' => false,
        'ativo' => true,
        'minimo_cotacoes' => 2,
    ]);
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value,
    ]);

    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Gestor->value,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000001',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'triagem_iniciada_em' => now(),
    ]);

    $f1 = criarFornecedorHomologado();
    $f2 = criarFornecedorHomologado();

    $this->actingAs($compradora);

    $c1 = app(RegistrarCotacaoAction::class)->execute($requisicao, $f1, 1000.00);
    app(RegistrarCotacaoAction::class)->execute($requisicao, $f2, 900.00);
    app(MarcarCotacaoVencedoraAction::class)->execute($requisicao, $c1);

    app(ConcluirCotacaoAction::class)->execute($requisicao);

    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and($requisicao->fresh()->cotacao_concluida_em)->not->toBeNull();
});

it('emergencial_pode_concluir_com_1_cotacao', function () {
    Mail::fake();
    $compradora = criarCompradora();
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'is_emergencial' => true,
        'ativo' => true,
        'minimo_cotacoes' => 3,
    ]);
    // Emergencial: Diretor é obrigatório — faixa só tem Gestor, portanto IniciarAprovacaoAction prepende Diretor
    EtapaAlcada::factory()->create([
        'faixa_alcada_id' => $faixa->id,
        'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value,
    ]);

    $aprovadorDiretor = User::factory()->create();
    $aprovadorDiretor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Diretor->value,
    ]);
    $aprovadorGestor = User::factory()->create();
    $aprovadorGestor->unidades()->attach($unidade->id, [
        'perfil' => Perfil::Aprovador->value,
        'nivel_alcada' => NivelAlcada::Gestor->value,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'urgente' => true,
        'is_emergencial' => true,
        'codigo' => 'REQ-2026-000099',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'triagem_iniciada_em' => now(),
    ]);

    $fornecedor = criarFornecedorHomologado();

    $this->actingAs($compradora);

    $cotacao = app(RegistrarCotacaoAction::class)->execute($requisicao, $fornecedor, 5000.00);
    app(MarcarCotacaoVencedoraAction::class)->execute($requisicao, $cotacao);
    app(ConcluirCotacaoAction::class)->execute($requisicao);

    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao);
});

it('cotacao_concluida_avanca_para_aguardando_aprovacao', function () {
    $compradora = criarCompradora();

    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'is_emergencial' => false,
        'ativo' => true,
        'minimo_cotacoes' => 1,
    ]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000002',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now(),
        'triagem_iniciada_em' => now(),
        'cotacao_concluida_em' => now(),
    ]);

    $this->actingAs($compradora);

    app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::AguardandoAprovacao);

    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao);
});

it('faixa_alcada_tem_minimo_cotacoes_com_default_3', function () {
    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0,
        'is_emergencial' => false,
        'ativo' => true,
    ]);

    expect($faixa->minimo_cotacoes)->toBe(3);
});
