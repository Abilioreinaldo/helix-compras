<?php

use App\Actions\AprovarEtapaAction;
use App\Actions\CriarRascunhoPedidoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Aprovacoes\PainelAprovacao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Monta uma requisição em AguardandoAprovacao com N itens e as etapas/aprovadores
 * dos níveis informados (na ordem dada).
 *
 * @param  array<int, NivelAlcada>  $niveis
 * @return array{0: Requisicao, 1: Collection<int, ItemRequisicao>, 2: array<string, User>, 3: Unidade}
 */
function ldCenario(array $niveis = [NivelAlcada::Gestor], int $numItens = 3): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);

    $req = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
    ]);

    $itens = ItemRequisicao::factory()->count($numItens)->create([
        'requisicao_id' => $req->id,
        'item_catalogo_id' => null,
        'avulso' => true,
        'quantidade' => 1,
        'valor_unitario_estimado' => 100,
    ]);

    $aprovadores = [];
    foreach ($niveis as $i => $nivel) {
        Aprovacao::create([
            'requisicao_id' => $req->id,
            'etapa_alcada_id' => null,
            'ciclo' => 1,
            'ordem' => $i + 1,
            'nivel_exigido' => $nivel->value,
            'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente->value,
        ]);
        $u = User::factory()->create();
        $u->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => $nivel->value]);
        $aprovadores[$nivel->value] = $u;
    }

    return [$req, $itens, $aprovadores, $unidade];
}

// ─── Decisão por linha ───────────────────────────────────────────────────────

it('aprova rejeitando um item específico sem reprovar a requisição', function () {
    Mail::fake();
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor]);
    $alvo = $itens->first();

    app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', [$alvo->id => 'Fora do orçamento']
    );

    $alvo->refresh();
    $reqAtual = Requisicao::withoutGlobalScopes()->find($req->id);

    expect($alvo->estaRejeitado())->toBeTrue()
        ->and($alvo->motivo_rejeicao)->toBe('Fora do orçamento')
        ->and($alvo->rejeitado_por)->toBe($aprovadores[NivelAlcada::Gestor->value]->id)
        ->and($reqAtual->status)->toBe(StatusRequisicao::Aprovada)
        ->and($reqAtual->itens()->whereNull('rejeitado_em')->count())->toBe(2);
});

it('não permite rejeitar todos os itens (isso é reprovação)', function () {
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor]);
    $todos = $itens->mapWithKeys(fn ($i) => [$i->id => 'motivo'])->all();

    expect(fn () => app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', $todos
    ))->toThrow(ValidationException::class);

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and(ItemRequisicao::whereNotNull('rejeitado_em')->count())->toBe(0);
});

it('exige motivo ao rejeitar um item', function () {
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor]);

    expect(fn () => app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', [$itens->first()->id => '   ']
    ))->toThrow(ValidationException::class);

    expect(ItemRequisicao::whereNotNull('rejeitado_em')->count())->toBe(0);
});

it('não burla a alçada: rejeitar item não encurta a cadeia de aprovação', function () {
    Mail::fake();
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor, NivelAlcada::Diretor]);

    // Gestor aprova sua etapa rejeitando um item — ainda falta o Diretor.
    app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', [$itens->first()->id => 'cortar']
    );

    $reqAtual = Requisicao::withoutGlobalScopes()->find($req->id);
    $pendentes = Aprovacao::where('requisicao_id', $req->id)->where('status', StatusAprovacao::Pendente->value)->count();

    expect($reqAtual->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and($pendentes)->toBe(1)
        ->and($itens->first()->fresh()->estaRejeitado())->toBeTrue();
});

it('valorAprovado exclui os itens rejeitados', function () {
    Mail::fake();
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor]); // 3 itens × R$100 = 300

    app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', [$itens->first()->id => 'cortar']
    );

    $reqAtual = Requisicao::withoutGlobalScopes()->with('itens')->find($req->id);

    expect($reqAtual->valorTotal())->toBe(300.0)
        ->and($reqAtual->valorAprovado())->toBe(200.0);
});

it('o pedido de compra não inclui os itens rejeitados', function () {
    Mail::fake();
    [$req, $itens, $aprovadores, $unidade] = ldCenario([NivelAlcada::Gestor]);

    app(AprovarEtapaAction::class)->execute(
        $req, $aprovadores[NivelAlcada::Gestor->value], 'ok', [$itens->first()->id => 'cortar']
    );

    $fornecedor = Fornecedor::factory()->homologado()->create();
    Cotacao::create([
        'requisicao_id' => $req->id,
        'fornecedor_id' => $fornecedor->id,
        'valor' => 200,
        'vencedora' => true,
        'criada_por' => $aprovadores[NivelAlcada::Gestor->value]->id,
    ]);

    $compradora = User::factory()->compradora()->create();
    $reqAprovada = Requisicao::withoutGlobalScopes()->find($req->id);

    $pedido = app(CriarRascunhoPedidoAction::class)->execute($fornecedor, collect([$reqAprovada]), $compradora);

    expect($pedido->itens()->count())->toBe(2); // 3 itens − 1 rejeitado
});

it('painel: aprovador rejeita item pelo modal de aprovação', function () {
    Mail::fake();
    [$req, $itens, $aprovadores] = ldCenario([NivelAlcada::Gestor]);
    $alvo = $itens->first();
    $aprovador = $aprovadores[NivelAlcada::Gestor->value];

    Livewire::actingAs($aprovador)
        ->test(PainelAprovacao::class, ['id' => $req->id])
        ->set('rejeitar', [$alvo->id => true])
        ->set('motivoRejeicao', [$alvo->id => 'duplicado'])
        ->call('aprovar')
        ->assertHasNoErrors();

    expect($alvo->fresh()->estaRejeitado())->toBeTrue()
        ->and(Requisicao::withoutGlobalScopes()->find($req->id)->status)->toBe(StatusRequisicao::Aprovada);
});
