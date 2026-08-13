<?php

use App\Livewire\Admin\Alcadas\ListaAlcadas;
use App\Livewire\Admin\CentrosCusto\ListaCentrosCusto;
use App\Livewire\Admin\Fornecedores\ListaFornecedores;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\CentroCusto;
use App\Models\EtapaAlcada;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

// ─── Migrations e estrutura de tabelas ────────────────────────────────────────

test('tabela fornecedores possui as colunas esperadas', function () {
    expect(Schema::hasColumns('fornecedores', [
        'id',
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'categoria',
        'contato_nome',
        'contato_email',
        'contato_telefone',
        'homologado',
        'homologado_em',
        'homologado_por',
        'ativo',
        'observacoes',
        'deleted_at',
    ]))->toBeTrue();
});

test('tabela centros_custo possui as colunas esperadas', function () {
    expect(Schema::hasColumns('centros_custo', [
        'id',
        'unidade_id',
        'codigo',
        'nome',
        'gestor_id',
        'ativo',
        'deleted_at',
    ]))->toBeTrue();
});

// ─── Autorização ──────────────────────────────────────────────────────────────

test('admin acessa /admin/unidades com sucesso', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/unidades')->assertOk();
});

test('nao-admin recebe 403 em /admin/unidades', function () {
    $usuario = User::factory()->create(['is_admin' => false]);

    $this->actingAs($usuario)->get('/admin/unidades')->assertForbidden();
});

test('compradora recebe 403 em /admin/fornecedores', function () {
    $compradora = User::factory()->compradora()->create();

    $this->actingAs($compradora)->get('/admin/fornecedores')->assertForbidden();
});

// ─── CRUD Fornecedor ──────────────────────────────────────────────────────────

test('admin cria fornecedor via livewire e persiste no banco', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaFornecedores::class)
        ->call('abrirCriar')
        ->set('razaoSocial', 'Teste Fornecedor Ltda')
        ->set('cnpj', '12345678000195')
        ->set('categoria', 'materiais')
        ->call('salvar');

    expect(Fornecedor::where('razao_social', 'Teste Fornecedor Ltda')->exists())->toBeTrue();
});

test('admin homologa fornecedor e campos sao preenchidos corretamente', function () {
    $admin = User::factory()->admin()->create();
    $fornecedor = Fornecedor::factory()->create(['homologado' => false]);

    Livewire::actingAs($admin)
        ->test(ListaFornecedores::class)
        ->call('homologar', $fornecedor->id);

    $fornecedor->refresh();

    expect($fornecedor->homologado)->toBeTrue()
        ->and($fornecedor->homologado_em)->not->toBeNull()
        ->and($fornecedor->homologado_por)->toBe($admin->id);
});

// ─── CRUD Usuário ─────────────────────────────────────────────────────────────

test('admin cria usuario com precisa_trocar_senha true e senha nao nula', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('name', 'Novo Usuário Teste')
        ->set('email', 'novo@comendador.com.br')
        ->call('salvar');

    $usuario = User::where('email', 'novo@comendador.com.br')->first();

    expect($usuario)->not->toBeNull()
        ->and($usuario->precisa_trocar_senha)->toBeTrue()
        ->and($usuario->password)->not->toBeNull();
});

// ─── CRUD Centro de Custo ─────────────────────────────────────────────────────

test('admin cria centro de custo e persiste com unidade_id correto', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListaCentrosCusto::class)
        ->call('abrirCriar')
        ->set('unidadeId', $unidade->id)
        ->set('codigo', 'CC-TESTE')
        ->set('nome', 'Centro Teste')
        ->call('salvar');

    $centro = CentroCusto::withoutGlobalScopes()->where('codigo', 'CC-TESTE')->first();

    expect($centro)->not->toBeNull()
        ->and($centro->unidade_id)->toBe($unidade->id);
});

test('admin tenta criar centro de custo com codigo duplicado na mesma unidade e recebe erro de validacao', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    CentroCusto::withoutGlobalScopes()->create([
        'unidade_id' => $unidade->id,
        'codigo' => 'CC-DUP',
        'nome' => 'Existente',
        'ativo' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ListaCentrosCusto::class)
        ->call('abrirCriar')
        ->set('unidadeId', $unidade->id)
        ->set('codigo', 'CC-DUP')
        ->set('nome', 'Duplicado')
        ->call('salvar')
        ->assertHasErrors(['codigo']);
});

test('codigo duplicado em unidade diferente nao gera erro', function () {
    $admin = User::factory()->admin()->create();
    $unidadeA = Unidade::factory()->create();
    $unidadeB = Unidade::factory()->create();
    CentroCusto::withoutGlobalScopes()->create([
        'unidade_id' => $unidadeA->id,
        'codigo' => 'CC-MULTI',
        'nome' => 'Unidade A',
        'ativo' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ListaCentrosCusto::class)
        ->call('abrirCriar')
        ->set('unidadeId', $unidadeB->id)
        ->set('codigo', 'CC-MULTI')
        ->set('nome', 'Unidade B')
        ->call('salvar')
        ->assertHasNoErrors();
});

// ─── CRUD Alçadas ─────────────────────────────────────────────────────────────

test('admin cria faixa de alcada com 2 etapas e etapas sao salvas com ordem 1 e 2', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ListaAlcadas::class)
        ->call('abrirCriar')
        ->set('nome', 'Faixa Teste')
        ->set('valorMinimo', '0')
        ->set('valorMaximo', '10000')
        ->call('adicionarEtapa')
        ->call('adicionarEtapa')
        ->set('etapas.0.nivel_exigido', 'gestor')
        ->set('etapas.1.nivel_exigido', 'diretor')
        ->call('salvar');

    $faixa = FaixaAlcada::where('nome', 'Faixa Teste')->first();
    expect($faixa)->not->toBeNull();

    $etapas = EtapaAlcada::where('faixa_alcada_id', $faixa->id)->orderBy('ordem')->get();
    expect($etapas)->toHaveCount(2)
        ->and($etapas->first()->ordem)->toBe(1)
        ->and($etapas->last()->ordem)->toBe(2);
});

// ─── CRUD Unidade / Obra ──────────────────────────────────────────────────────

test('admin edita unidade do tipo obra e atualiza obra vinculada', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->obra()->create();
    Obra::create([
        'unidade_id' => $unidade->id,
        'iniciada_em' => '2024-01-01',
        'previsao_termino' => '2025-12-31',
        'status' => 'ativa',
        'verba' => 100000.00,
    ]);

    Livewire::actingAs($admin)
        ->test(ListaUnidades::class)
        ->call('abrirEditar', $unidade->id)
        ->set('obraVerba', '250000')
        ->set('obraPrevisaoTermino', '2027-06-30')
        ->call('salvar');

    $obraAtualizada = Obra::withoutGlobalScopes()->where('unidade_id', $unidade->id)->first();
    expect((float) $obraAtualizada->verba)->toBe(250000.0)
        ->and($obraAtualizada->previsao_termino->format('Y-m-d'))->toBe('2027-06-30');
});

// ─── Visibilidade (regressão de scope) ────────────────────────────────────────

test('solicitante nao ve centro de custo de outra unidade', function () {
    $solicitante = User::factory()->create(['is_admin' => false]);
    $minhaUnidade = Unidade::factory()->create();
    $outraUnidade = Unidade::factory()->create();

    // Vincula o solicitante à sua unidade
    $minhaUnidade->usuarios()->attach($solicitante->id, [
        'perfil' => 'solicitante',
        'nivel_alcada' => null,
    ]);

    CentroCusto::withoutGlobalScopes()->create([
        'unidade_id' => $minhaUnidade->id,
        'codigo' => 'MEU-CC',
        'nome' => 'Meu Centro',
        'ativo' => true,
    ]);

    CentroCusto::withoutGlobalScopes()->create([
        'unidade_id' => $outraUnidade->id,
        'codigo' => 'OUTRO-CC',
        'nome' => 'Outro Centro',
        'ativo' => true,
    ]);

    $this->actingAs($solicitante);

    // O scope deve limitar à unidade do solicitante
    $centrosVisiveis = CentroCusto::all();

    expect($centrosVisiveis->pluck('codigo')->contains('OUTRO-CC'))->toBeFalse()
        ->and($centrosVisiveis->pluck('codigo')->contains('MEU-CC'))->toBeTrue();
});
