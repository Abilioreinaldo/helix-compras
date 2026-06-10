<?php

use App\Enums\Perfil;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TrocarSenha;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Autenticação
// ---------------------------------------------------------------------------

test('login com credenciais válidas redireciona para dashboard', function () {
    $user = User::factory()->create(['password' => bcrypt('senha@123')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('senha', 'senha@123')
        ->call('autenticar')
        ->assertRedirect(route('dashboard'));
});

test('login com credenciais inválidas retorna erro genérico', function () {
    $user = User::factory()->create(['password' => bcrypt('senha@123')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('senha', 'senha_errada')
        ->call('autenticar')
        ->assertHasErrors(['email' => 'Credenciais inválidas.']);
});

test('login com precisa_trocar_senha redireciona para troca de senha', function () {
    $user = User::factory()->precisaTrocarSenha()->create(['password' => bcrypt('senha@123')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('senha', 'senha@123')
        ->call('autenticar')
        ->assertRedirect(route('senha.trocar'));
});

test('middleware bloqueia dashboard enquanto precisa_trocar_senha for true', function () {
    $user = User::factory()->precisaTrocarSenha()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('senha.trocar'));
});

test('troca de senha com senha atual errada retorna erro', function () {
    $user = User::factory()->create(['password' => bcrypt('senha@123')]);

    Livewire::actingAs($user)
        ->test(TrocarSenha::class)
        ->set('senha_atual', 'senha_errada')
        ->set('nova_senha', 'nova_senha@123')
        ->set('nova_senha_confirmation', 'nova_senha@123')
        ->call('salvar')
        ->assertHasErrors(['senha_atual']);
});

test('troca de senha com sucesso desativa flag e redireciona para dashboard', function () {
    $user = User::factory()->precisaTrocarSenha()->create(['password' => bcrypt('senha@123')]);

    Livewire::actingAs($user)
        ->test(TrocarSenha::class)
        ->set('senha_atual', 'senha@123')
        ->set('nova_senha', 'nova_senha@123')
        ->set('nova_senha_confirmation', 'nova_senha@123')
        ->call('salvar')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->precisa_trocar_senha)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Visibilidade por unidade (UnidadeScope)
// ---------------------------------------------------------------------------

test('solicitante da unidade A não enxerga unidade B', function () {
    $unidadeA = Unidade::factory()->create(['nome' => 'Unidade A']);
    $unidadeB = Unidade::factory()->create(['nome' => 'Unidade B']);

    $solicitante = User::factory()->create();
    $unidadeA->usuarios()->attach($solicitante->id, [
        'perfil' => Perfil::Solicitante->value,
        'nivel_alcada' => null,
    ]);

    $this->actingAs($solicitante);

    $visivel = Unidade::all();

    expect($visivel->pluck('id'))->toContain($unidadeA->id)
        ->and($visivel->pluck('id'))->not->toContain($unidadeB->id);
});

test('admin enxerga todas as unidades sem filtro', function () {
    Unidade::factory()->count(3)->create();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect(Unidade::count())->toBe(3);
});

test('compradora enxerga todas as unidades sem filtro', function () {
    Unidade::factory()->count(3)->create();

    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    expect(Unidade::count())->toBe(3);
});

test('UnidadeScope sem usuário autenticado retorna zero linhas', function () {
    Unidade::factory()->count(2)->create();

    // Sem actingAs — nenhum usuário autenticado
    expect(Unidade::count())->toBe(0);
});

test('usuário sem vínculo com nenhuma unidade retorna zero linhas', function () {
    Unidade::factory()->count(2)->create();

    $semVinculo = User::factory()->create();
    $this->actingAs($semVinculo);

    expect(Unidade::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Auditoria
// ---------------------------------------------------------------------------

test('atualizar uma Unidade registra entrada na tabela auditorias', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $unidade = Unidade::factory()->create(['nome' => 'Nome Original']);
    $unidade->update(['nome' => 'Nome Alterado']);

    $this->assertDatabaseHas('auditorias', [
        'auditavel_type' => 'unidade',
        'auditavel_id' => $unidade->id,
        'campo' => 'nome',
        'valor_anterior' => 'Nome Original',
        'valor_novo' => 'Nome Alterado',
        'evento' => 'atualizado',
        'user_id' => $admin->id,
    ]);
});

test('criar uma Unidade registra evento criado na tabela auditorias', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $unidade = Unidade::factory()->create();

    $this->assertDatabaseHas('auditorias', [
        'auditavel_type' => 'unidade',
        'auditavel_id' => $unidade->id,
        'evento' => 'criado',
        'user_id' => $admin->id,
    ]);
});

test('auditoria registra user_id null quando não há usuário autenticado', function () {
    $unidade = Unidade::factory()->create();

    $this->assertDatabaseHas('auditorias', [
        'auditavel_type' => 'unidade',
        'auditavel_id' => $unidade->id,
        'evento' => 'criado',
        'user_id' => null,
    ]);
});
