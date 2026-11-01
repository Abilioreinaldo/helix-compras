<?php

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Admin\Fornecedores\ListaFornecedores;
use App\Livewire\Aprovacoes\FilaAprovacoes;
use App\Livewire\Aprovacoes\PainelAprovacao;
use App\Livewire\Requisicoes\DetalheRequisicao;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
| Aceite em produção (25/09/2026) — itens de usabilidade apontados no relatório.
*/

uses(RefreshDatabase::class);

function ua_requisicaoAguardando(User $solicitante, Unidade $unidade, string $codigo): Requisicao
{
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
        'requisicao_id' => $req->id, 'ciclo' => 1, 'ordem' => 1, 'nivel_exigido' => 'gestor',
        'obrigatoria_emergencial' => false, 'status' => StatusAprovacao::Pendente->value,
    ]);

    return $req;
}

it('status das requisições saem acentuados nas telas', function () {
    expect(StatusRequisicao::CotacaoConcluida->label())->toBe('Cotação concluída')
        ->and(StatusRequisicao::AguardandoAprovacao->label())->toBe('Aguardando aprovação')
        ->and(StatusAprovacao::Pendente->label())->toBe('Pendente');

    $aprovador = User::factory()->create();
    $unidade = Unidade::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => 'gestor']);
    $req = ua_requisicaoAguardando(User::factory()->create(), $unidade, 'REQ-UA-ACENTO');

    Livewire::actingAs($aprovador)
        ->test(DetalheRequisicao::class, ['id' => $req->id])
        ->assertSee('Aguardando aprovação')
        ->assertDontSee('Aguardando Aprovacao');
});

it('a própria requisição sai da fila do aprovador e vira aviso', function () {
    $unidade = Unidade::factory()->create();
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => 'gestor']);
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    ua_requisicaoAguardando($aprovador, $unidade, 'REQ-UA-MINHA');
    ua_requisicaoAguardando(User::factory()->create(), $unidade, 'REQ-UA-OUTRO');

    Livewire::actingAs($aprovador)
        ->test(FilaAprovacoes::class)
        ->assertSee('REQ-UA-OUTRO')
        ->assertDontSee('REQ-UA-MINHA')
        ->assertSee('1 requisição sua aguarda aprovação de outro aprovador');
});

it('no painel, item sem decisão aparece como "Em análise", não "Aprovado"', function () {
    $unidade = Unidade::factory()->create();
    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => 'gestor']);
    $req = ua_requisicaoAguardando(User::factory()->create(), $unidade, 'REQ-UA-ANALISE');
    $req->itens()->create(['descricao' => 'Cimento', 'quantidade' => 10, 'unidade_medida' => 'saco', 'valor_unitario_estimado' => 30, 'avulso' => true]);

    Livewire::actingAs($aprovador)
        ->test(PainelAprovacao::class, ['id' => $req->id])
        ->assertSee('Em análise')
        ->assertDontSee('>Aprovado<', false);
});

it('homologar fornecedor passa por modal do sistema e grava quem homologou', function () {
    $admin = User::factory()->admin()->create();
    $fornecedor = Fornecedor::factory()->create(['homologado' => false, 'razao_social' => 'TESTE Cimentos União', 'nome_fantasia' => null]);

    Livewire::actingAs($admin)
        ->test(ListaFornecedores::class)
        ->call('abrirHomologacao', $fornecedor->id)
        ->assertSet('homologandoId', $fornecedor->id)
        ->assertSee('Homologar fornecedor')
        ->assertSee('TESTE Cimentos União')
        ->call('homologar', $fornecedor->id)
        ->assertSet('homologandoId', null);

    expect($fornecedor->fresh()->homologado)->toBeTrue()
        ->and($fornecedor->fresh()->homologado_por)->toBe($admin->id);
});

it('o detalhe mostra a obra pelo nome da unidade, não pelo id', function () {
    $unidade = Unidade::factory()->create(['nome' => 'Obra Expansão Norte']);
    $obra = Obra::create(['unidade_id' => $unidade->id, 'iniciada_em' => '2026-09-25', 'status' => 'ativa']);
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $req = ua_requisicaoAguardando($solicitante, $unidade, 'REQ-UA-OBRA');
    $req->update(['obra_id' => $obra->id]);

    Livewire::actingAs($solicitante)
        ->test(DetalheRequisicao::class, ['id' => $req->id])
        ->assertSee('Obra Expansão Norte')
        ->assertSee('desde 25/09/2026');
});
