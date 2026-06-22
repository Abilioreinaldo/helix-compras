<?php

use App\Enums\StatusPagamento;
use App\Livewire\Financeiro\ListaPagamentos;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('financeiro vê a lista de pagamentos', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pag = Pagamento::factory()->create();

    Livewire::actingAs($financeiro)
        ->test(ListaPagamentos::class)
        ->assertOk()
        ->assertSee($pag->fornecedor->nome_fantasia);
});

it('não-financeiro recebe 403 em /pagamentos', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('pagamentos.index'))->assertForbidden();
});

it('registra pagamento pela lista (modal)', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pag = Pagamento::factory()->create(['valor_total' => 500, 'status' => StatusPagamento::Pendente]);

    Livewire::actingAs($financeiro)
        ->test(ListaPagamentos::class)
        ->call('abrirRegistrar', $pag->id)
        ->set('metodo', 'transferencia')
        ->call('registrar')
        ->assertHasNoErrors();

    expect($pag->fresh()->status)->toBe(StatusPagamento::Pago);
});
