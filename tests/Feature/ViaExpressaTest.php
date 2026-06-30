<?php

use App\Actions\AtenderViaExpressaAction;
use App\Actions\SubmeterRequisicaoAction;
use App\Enums\NivelAlcada;
use App\Enums\OrigemCotacao;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Livewire\Compradora\TriagemRequisicoes;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Models\Aprovacao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PrecoHomologado;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** @return array{0: Unidade, 1: User, 2: CentroCusto} */
function veCenario(): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    return [$unidade, $solicitante, $centro];
}

function veCatalogadoComPreco(Fornecedor $fornecedor, float $preco): CatalogoItem
{
    $item = CatalogoItem::factory()->create();
    PrecoHomologado::factory()->create([
        'item_catalogo_id' => $item->id,
        'fornecedor_id' => $fornecedor->id,
        'preco' => $preco,
    ]);

    return $item;
}

function veItem(Requisicao $req, ?CatalogoItem $catalogo, float $qtd, float $estimado): ItemRequisicao
{
    return ItemRequisicao::factory()->create([
        'requisicao_id' => $req->id,
        'item_catalogo_id' => $catalogo?->id,
        'avulso' => $catalogo === null,
        'quantidade' => $qtd,
        'valor_unitario_estimado' => $estimado,
    ]);
}

// ─── Elegibilidade (avaliarViaExpressa) ──────────────────────────────────────

it('é elegível quando todos os itens têm preço homologado válido do mesmo fornecedor', function () {
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat1 = veCatalogadoComPreco($fornecedor, 10.00);
    $cat2 = veCatalogadoComPreco($fornecedor, 20.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create();
    veItem($req, $cat1, 2, 10.00);
    veItem($req, $cat2, 1, 20.00);

    $avaliacao = $req->avaliarViaExpressa();

    expect($avaliacao)->not->toBeNull()
        ->and($avaliacao['fornecedor_id'])->toBe($fornecedor->id)
        ->and($avaliacao['precos'])->toHaveCount(2);
});

it('não é elegível quando há item avulso (sem catálogo)', function () {
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 10.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create();
    veItem($req, $cat, 2, 10.00);
    veItem($req, null, 1, 50.00); // avulso

    expect($req->avaliarViaExpressa())->toBeNull();
});

it('não é elegível quando a homologação está vencida', function () {
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = CatalogoItem::factory()->create();
    PrecoHomologado::factory()->vencido()->create([
        'item_catalogo_id' => $cat->id,
        'fornecedor_id' => $fornecedor->id,
        'preco' => 10.00,
    ]);

    $req = Requisicao::factory()->aguardandoTriagem()->create();
    veItem($req, $cat, 2, 10.00);

    expect($req->avaliarViaExpressa())->toBeNull();
});

it('não é elegível quando os itens resolvem para fornecedores diferentes', function () {
    $f1 = Fornecedor::factory()->homologado()->create();
    $f2 = Fornecedor::factory()->homologado()->create();
    $cat1 = veCatalogadoComPreco($f1, 10.00);
    $cat2 = veCatalogadoComPreco($f2, 20.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create();
    veItem($req, $cat1, 1, 10.00);
    veItem($req, $cat2, 1, 20.00);

    expect($req->avaliarViaExpressa())->toBeNull();
});

it('desempata pelo fornecedor preferencial quando há mais de uma homologação', function () {
    $preferido = Fornecedor::factory()->homologado()->create();
    $outro = Fornecedor::factory()->homologado()->create();
    $cat = CatalogoItem::factory()->create();

    // Outro é mais barato, mas o preferido vence o desempate.
    PrecoHomologado::factory()->create(['item_catalogo_id' => $cat->id, 'fornecedor_id' => $outro->id, 'preco' => 5.00]);
    PrecoHomologado::factory()->preferencial()->create(['item_catalogo_id' => $cat->id, 'fornecedor_id' => $preferido->id, 'preco' => 9.00]);

    $req = Requisicao::factory()->aguardandoTriagem()->create();
    veItem($req, $cat, 1, 9.00);

    expect($req->avaliarViaExpressa()['fornecedor_id'])->toBe($preferido->id);
});

// ─── Submissão grava o flag ──────────────────────────────────────────────────

it('submeter grava expressa=true para requisição totalmente homologada', function () {
    [$unidade, $solicitante, $centro] = veCenario();
    FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 50.00);

    $req = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Rascunho,
    ]);
    veItem($req, $cat, 2, 50.00);

    $this->actingAs($solicitante);
    app(SubmeterRequisicaoAction::class)->execute($req);

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->expressa)->toBeTrue();
});

it('submeter grava expressa=false quando há item avulso', function () {
    [$unidade, $solicitante, $centro] = veCenario();
    FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 50.00);

    $req = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Rascunho,
    ]);
    veItem($req, $cat, 2, 50.00);
    veItem($req, null, 1, 30.00);

    $this->actingAs($solicitante);
    app(SubmeterRequisicaoAction::class)->execute($req);

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->expressa)->toBeFalse();
});

// ─── Atendimento via expressa ────────────────────────────────────────────────

it('atender gera cotação homologada vencedora e inicia a aprovação', function () {
    Mail::fake();
    [$unidade, $solicitante, $centro] = veCenario();

    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);
    EtapaAlcada::factory()->create(['faixa_alcada_id' => $faixa->id, 'ordem' => 1, 'nivel_exigido' => NivelAlcada::Gestor->value]);
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 100.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'faixa_alcada_id' => $faixa->id,
        'expressa' => true,
    ]);
    veItem($req, $cat, 3, 100.00);

    $compradora = User::factory()->create(['is_compradora' => true]);
    $this->actingAs($compradora);

    app(AtenderViaExpressaAction::class)->execute($req, $compradora);

    $reqAtual = Requisicao::withoutGlobalScopes()->find($req->id);
    $cotacao = Cotacao::where('requisicao_id', $req->id)->first();

    expect($reqAtual->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and($cotacao->origem)->toBe(OrigemCotacao::Homologado)
        ->and($cotacao->vencedora)->toBeTrue()
        ->and((float) $cotacao->valor)->toBe(300.00)
        ->and($cotacao->fornecedor_id)->toBe($fornecedor->id)
        ->and($cotacao->itensCotacao()->count())->toBe(1)
        ->and(Aprovacao::where('requisicao_id', $req->id)->count())->toBe(1);
});

it('não burla a alçada: expressa de alto valor ainda exige Diretor + CEO', function () {
    Mail::fake();
    [$unidade, $solicitante, $centro] = veCenario();

    $faixaAlta = FaixaAlcada::factory()->create(['valor_minimo' => 20000.01, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);
    EtapaAlcada::factory()->create(['faixa_alcada_id' => $faixaAlta->id, 'ordem' => 1, 'nivel_exigido' => NivelAlcada::Diretor->value]);
    EtapaAlcada::factory()->create(['faixa_alcada_id' => $faixaAlta->id, 'ordem' => 2, 'nivel_exigido' => NivelAlcada::Ceo->value]);

    $diretor = User::factory()->create();
    $diretor->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Diretor->value]);
    $ceo = User::factory()->create();
    $ceo->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Ceo->value]);

    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 25000.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'faixa_alcada_id' => $faixaAlta->id,
        'expressa' => true,
    ]);
    veItem($req, $cat, 1, 25000.00);

    $compradora = User::factory()->create(['is_compradora' => true]);
    $this->actingAs($compradora);

    app(AtenderViaExpressaAction::class)->execute($req, $compradora);

    $niveis = Aprovacao::where('requisicao_id', $req->id)->orderBy('ordem')->pluck('nivel_exigido')->all();

    expect($niveis)->toBe([NivelAlcada::Diretor, NivelAlcada::Ceo]);
});

it('triagem: compradora atende via expressa em um clique', function () {
    Mail::fake();
    [$unidade, $solicitante, $centro] = veCenario();

    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 3]);
    EtapaAlcada::factory()->create(['faixa_alcada_id' => $faixa->id, 'ordem' => 1, 'nivel_exigido' => NivelAlcada::Gestor->value]);
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 100.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'faixa_alcada_id' => $faixa->id,
        'expressa' => true,
    ]);
    veItem($req, $cat, 2, 100.00);

    $compradora = User::factory()->compradora()->create();

    Livewire::actingAs($compradora)
        ->test(TriagemRequisicoes::class)
        ->call('atenderViaExpressa', $req->id)
        ->assertHasNoErrors();

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->status)
        ->toBe(StatusRequisicao::AguardandoAprovacao);
});

// ─── CRUD admin de homologação ───────────────────────────────────────────────

it('admin adiciona preço homologado a um item de catálogo', function () {
    $admin = User::factory()->admin()->create();
    $cat = CatalogoItem::factory()->create();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirModalHomologacoes', $cat->id)
        ->set('novoFornecedorId', (string) $fornecedor->id)
        ->set('novoPreco', '99.90')
        ->set('novaValidadeInicio', now()->toDateString())
        ->set('novaValidadeFim', now()->addDays(60)->toDateString())
        ->call('adicionarHomologacao')
        ->assertHasNoErrors();

    expect(PrecoHomologado::where('item_catalogo_id', $cat->id)->where('fornecedor_id', $fornecedor->id)->exists())->toBeTrue();
});

it('admin: marcar preferencial desmarca as demais do item', function () {
    $admin = User::factory()->admin()->create();
    $cat = CatalogoItem::factory()->create();
    $f1 = Fornecedor::factory()->homologado()->create();
    $f2 = Fornecedor::factory()->homologado()->create();

    PrecoHomologado::factory()->preferencial()->create(['item_catalogo_id' => $cat->id, 'fornecedor_id' => $f1->id]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirModalHomologacoes', $cat->id)
        ->set('novoFornecedorId', (string) $f2->id)
        ->set('novoPreco', '50.00')
        ->set('novaValidadeInicio', now()->toDateString())
        ->set('novaValidadeFim', now()->addDays(60)->toDateString())
        ->set('novoPreferencial', true)
        ->call('adicionarHomologacao')
        ->assertHasNoErrors();

    expect(PrecoHomologado::where('item_catalogo_id', $cat->id)->where('preferencial', true)->count())->toBe(1)
        ->and(PrecoHomologado::where('fornecedor_id', $f2->id)->first()->preferencial)->toBeTrue();
});

it('admin: rejeita homologação com fornecedor não homologado', function () {
    $admin = User::factory()->admin()->create();
    $cat = CatalogoItem::factory()->create();
    $fornecedor = Fornecedor::factory()->create(['homologado' => false]);

    Livewire::actingAs($admin)
        ->test(ListaCatalogoItens::class)
        ->call('abrirModalHomologacoes', $cat->id)
        ->set('novoFornecedorId', (string) $fornecedor->id)
        ->set('novoPreco', '10.00')
        ->set('novaValidadeInicio', now()->toDateString())
        ->set('novaValidadeFim', now()->addDays(60)->toDateString())
        ->call('adicionarHomologacao')
        ->assertHasErrors('novoFornecedorId');

    expect(PrecoHomologado::where('item_catalogo_id', $cat->id)->count())->toBe(0);
});

// ─── Formulário de requisição: autofill + preview ────────────────────────────

it('formulário: selecionar item de catálogo preenche o preço estimado homologado', function () {
    [$unidade, $solicitante, $centro] = veCenario();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 42.50);

    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->call('adicionarItem')
        ->call('selecionarItemCatalogo', 1, $cat->id)
        ->assertSet('itens.1.valor_unitario_estimado', '42.50')
        ->assertSet('itens.1.item_catalogo_id', $cat->id);
});

it('formulário: preview indica via expressa quando todos os itens são homologados', function () {
    [$unidade, $solicitante, $centro] = veCenario();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 20.00);

    Livewire::actingAs($solicitante)
        ->test(FormularioRequisicao::class)
        ->call('selecionarItemCatalogo', 0, $cat->id)
        ->call('previewExpressa')
        ->assertReturned(true);
});

// ─── Governança: expiração de homologações ───────────────────────────────────

it('comando expira homologações vencidas e preserva as válidas', function () {
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = CatalogoItem::factory()->create();

    $vencida = PrecoHomologado::factory()->vencido()->create(['item_catalogo_id' => $cat->id, 'fornecedor_id' => $fornecedor->id]);
    $valida = PrecoHomologado::factory()->create(['item_catalogo_id' => $cat->id, 'fornecedor_id' => $fornecedor->id]);

    $this->artisan('precos:expirar-homologacoes')->assertSuccessful();

    expect($vencida->fresh()->ativo)->toBeFalse()
        ->and($valida->fresh()->ativo)->toBeTrue();
});

it('rejeita atendimento expressa de requisição inelegível', function () {
    [$unidade, $solicitante, $centro] = veCenario();
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $cat = veCatalogadoComPreco($fornecedor, 100.00);

    $req = Requisicao::factory()->aguardandoTriagem()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
    ]);
    veItem($req, $cat, 1, 100.00);
    veItem($req, null, 1, 50.00); // avulso → inelegível

    $compradora = User::factory()->create(['is_compradora' => true]);
    $this->actingAs($compradora);

    expect(fn () => app(AtenderViaExpressaAction::class)->execute($req, $compradora))
        ->toThrow(ValidationException::class);
});
