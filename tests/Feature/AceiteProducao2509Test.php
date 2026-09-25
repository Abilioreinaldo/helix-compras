<?php

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Livewire\Compradora\GestaoCotacoes;
use App\Livewire\Requisicoes\DetalheRequisicao;
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
use Livewire\Livewire;

/*
| Aceite em produção (25/09/2026) — os cinco bugs encontrados no primeiro fluxo
| ponta a ponta com um único usuário. Um teste por bug, com o cenário do relatório.
*/

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

function requisicaoCotacaoConcluidaSemAprovador(): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true, 'minimo_cotacoes' => 1]);
    EtapaAlcada::factory()->create(['faixa_alcada_id' => $faixa->id, 'ordem' => 1, 'nivel_exigido' => NivelAlcada::Gestor->value]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::CotacaoConcluida,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-2026-000001',
        'faixa_alcada_id' => $faixa->id,
        'submetida_em' => now()->subHour(),
        'triagem_iniciada_em' => now()->subMinutes(50),
        'cotacao_concluida_em' => now()->subMinutes(5),
        'ciclo_aprovacao' => 1,
    ]);

    return compact('requisicao', 'unidade');
}

// Bug 1 — o select de filtro reaproveitava $tipo e os campos de obra nunca apareciam.
it('bug 1: escolher o tipo Obra mostra os campos da obra no formulário', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaUnidades::class)
        ->call('abrirCriar')
        ->set('tipo', 'obra')
        ->assertSee('Dados da Obra')
        ->assertSee('obraIniciadaEm');
});

// Bug 2 — fornecedor sem nome fantasia aparecia em branco nas telas de cotação.
it('bug 2: fornecedor sem nome fantasia é exibido pela razão social', function () {
    $f = Fornecedor::factory()->homologado()->create(['razao_social' => 'TESTE Cimentos União Ltda', 'nome_fantasia' => null]);
    $g = Fornecedor::factory()->homologado()->create(['razao_social' => 'X Ltda', 'nome_fantasia' => 'Construfácil']);

    expect($f->nome)->toBe('TESTE Cimentos União Ltda')
        ->and($g->nome)->toBe('Construfácil');
});

// Bug 3 — o segundo perfil na mesma unidade sobrescrevia o primeiro, sem aviso.
it('bug 3: um usuário acumula perfis na mesma unidade e remove um perfil por vez', function () {
    $admin = User::factory()->admin()->create();
    $pessoa = User::factory()->create();
    $unidade = Unidade::factory()->create();

    $tela = Livewire::actingAs($admin)
        ->test(ListaUsuarios::class)
        ->call('abrirVinculos', $pessoa->id)
        ->set('vincularUnidadeId', $unidade->id)->set('vincularPerfil', Perfil::Solicitante->value)->call('adicionarVinculo')
        ->set('vincularUnidadeId', $unidade->id)->set('vincularPerfil', Perfil::Aprovador->value)->set('vincularNivelAlcada', NivelAlcada::Gestor->value)->call('adicionarVinculo')
        ->set('vincularUnidadeId', $unidade->id)->set('vincularPerfil', Perfil::Almoxarife->value)->call('adicionarVinculo')
        ->assertHasNoErrors();

    $perfis = fn () => $pessoa->unidades()->where('unidades.id', $unidade->id)->get()->map(fn ($u) => $u->pivot->perfil)->sort()->values()->all();

    expect($perfis())->toBe(['almoxarife', 'aprovador', 'solicitante'])
        ->and($pessoa->temPerfil(Perfil::Solicitante))->toBeTrue()
        ->and($pessoa->temPerfil(Perfil::Aprovador))->toBeTrue();

    // Mesmo perfil de novo: só o nível muda, sem duplicar.
    $tela->set('vincularUnidadeId', $unidade->id)->set('vincularPerfil', Perfil::Aprovador->value)->set('vincularNivelAlcada', NivelAlcada::Diretor->value)->call('adicionarVinculo');
    expect($perfis())->toHaveCount(3)
        ->and($pessoa->unidades()->wherePivot('perfil', 'aprovador')->first()->pivot->nivel_alcada)->toBe('diretor');

    // Remover um perfil não leva os outros junto.
    $tela->call('removerVinculo', $unidade->id, Perfil::Solicitante->value);
    expect($perfis())->toBe(['almoxarife', 'aprovador']);
});

// Bug 4 — cotação concluída sem aprovador: a requisição ficava sem saída.
it('bug 4: depois de cadastrar o aprovador, a compradora inicia a aprovação pelo detalhe', function () {
    ['requisicao' => $requisicao, 'unidade' => $unidade] = requisicaoCotacaoConcluidaSemAprovador();
    $compradora = User::factory()->compradora()->create();

    // Sem aprovador: a tentativa explica e a requisição continua parada (não some).
    Livewire::actingAs($compradora)
        ->test(DetalheRequisicao::class, ['id' => $requisicao->id])
        ->call('iniciarAprovacao')
        ->assertHasErrors('aprovacao')
        ->assertSee('Não há aprovadores');
    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::CotacaoConcluida);

    // O link "Ver detalhes" de /cotacoes leva ao detalhe em vez de 403.
    Livewire::actingAs($compradora)
        ->test(GestaoCotacoes::class, ['id' => $requisicao->id])
        ->assertRedirect(route('requisicoes.detalhe', $requisicao->id));

    // Admin cadastra o aprovador; a compradora reinicia a aprovação.
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    Livewire::actingAs($compradora)
        ->test(DetalheRequisicao::class, ['id' => $requisicao->id])
        ->assertSee('Iniciar aprovação')
        ->call('iniciarAprovacao')
        ->assertHasNoErrors();

    expect($requisicao->fresh()->status)->toBe(StatusRequisicao::AguardandoAprovacao)
        ->and($requisicao->aprovacoes()->count())->toBe(1);
});

it('bug 4: quem não tem compras.manage não inicia a aprovação', function () {
    ['requisicao' => $requisicao, 'unidade' => $unidade] = requisicaoCotacaoConcluidaSemAprovador();
    // Vê a requisição (solicitante da unidade), mas não é compradora.
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    Livewire::actingAs($solicitante)
        ->test(DetalheRequisicao::class, ['id' => $requisicao->id])
        ->call('iniciarAprovacao')
        ->assertForbidden();
});

// Bug 5 — depois da cotação o detalhe mostrava R$ 0,00: só lia o valor estimado (opcional).
it('bug 5: com vencedora definida o detalhe mostra o preço cotado por item e o total da vencedora', function () {
    ['requisicao' => $requisicao] = requisicaoCotacaoConcluidaSemAprovador();
    $item = $requisicao->itens()->create(['descricao' => 'Cimento CP-II 50kg', 'quantidade' => 200, 'unidade_medida' => 'saco', 'valor_unitario_estimado' => null, 'avulso' => true]);
    $fornecedor = Fornecedor::factory()->homologado()->create(['razao_social' => 'TESTE Cimentos União', 'nome_fantasia' => null]);
    $vencedora = Cotacao::factory()->create(['requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => 6360, 'vencedora' => true]);
    $vencedora->itensCotacao()->create(['item_requisicao_id' => $item->id, 'valor_unitario' => 31.80]);

    expect($requisicao->fresh()->load('cotacoes.itensCotacao')->valorUnitarioCotado($item))->toBe(31.8);

    Livewire::actingAs(User::factory()->compradora()->create())
        ->test(DetalheRequisicao::class, ['id' => $requisicao->id])
        ->assertSee('Valor unit. cotado')
        ->assertSee('R$ 31,80')
        ->assertSee('R$ 6.360,00')
        ->assertSee('TESTE Cimentos União')
        ->assertSee('200 saco')
        ->assertDontSee('200.000 saco');
});
