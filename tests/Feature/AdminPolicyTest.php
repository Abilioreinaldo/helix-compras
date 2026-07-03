<?php

use App\Enums\Perfil;
use App\Livewire\Admin\Fornecedores\ListaFornecedores;
use App\Models\Fornecedor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| AdminPolicy — Gate `admin.gerenciar` (cadastros/parâmetros).
|--------------------------------------------------------------------------
|
| Espelha temPerfil(Admin) (is_admin) — a mesma checagem do middleware `admin` das rotas.
| Só acesso: as validações de negócio dos cadastros seguem nos $this->validate()/Actions.
|
*/

uses(RefreshDatabase::class);

it('admin pode gerenciar cadastros', function () {
    expect(User::factory()->admin()->create()->can('admin.gerenciar'))->toBeTrue();
});

it('não-admin (compradora, solicitante, comum) não gerencia cadastros', function () {
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach(Unidade::factory()->create()->id, ['perfil' => Perfil::Solicitante->value]);

    expect(User::factory()->compradora()->create()->can('admin.gerenciar'))->toBeFalse()
        ->and($solicitante->can('admin.gerenciar'))->toBeFalse()
        ->and(User::factory()->create()->can('admin.gerenciar'))->toBeFalse();
});

it('ação de cadastro (excluir fornecedor) exige admin — usuário comum recebe 403', function () {
    // O acesso de VIEW às telas /admin é do middleware `admin` da rota; o gate no componente
    // protege as AÇÕES (defesa em profundidade). Aqui exercitamos a ação diretamente.
    Livewire::actingAs(User::factory()->create())
        ->test(ListaFornecedores::class)
        ->call('excluir', 1)
        ->assertForbidden();
});

it('admin executa ação de cadastro sem 403', function () {
    $fornecedor = Fornecedor::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(ListaFornecedores::class)
        ->call('excluir', $fornecedor->id)
        ->assertHasNoErrors();

    expect(Fornecedor::find($fornecedor->id))->toBeNull();
});
