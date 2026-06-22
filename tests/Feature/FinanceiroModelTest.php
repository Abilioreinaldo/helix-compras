<?php

use App\Enums\Perfil;
use App\Enums\StatusPagamento;
use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calcularTotal soma juros e multa e subtrai desconto', function () {
    $p = Pagamento::factory()->create([
        'valor_total' => 1000,
        'valor_desconto' => 50,
        'valor_juros' => 30,
        'valor_multa' => 20,
    ]);

    // 1000 - 50 + 30 + 20 = 1000
    expect($p->calcularTotal())->toBe(1000.00);
});

it('ehVencido só quando está em aberto e o vencimento passou', function () {
    $vencido = Pagamento::factory()->vencido()->create(['status' => StatusPagamento::Pendente]);
    $pagoNoPassado = Pagamento::factory()->vencido()->create(['status' => StatusPagamento::Pago]);
    $futuro = Pagamento::factory()->create(['status' => StatusPagamento::Pendente]);

    expect($vencido->ehVencido())->toBeTrue()
        ->and($pagoNoPassado->ehVencido())->toBeFalse()
        ->and($futuro->ehVencido())->toBeFalse();
});

it('não marca como vencido o pagamento que vence hoje', function () {
    $p = Pagamento::factory()->create(['status' => StatusPagamento::Pendente, 'data_vencimento' => now()->toDateString()]);
    expect($p->ehVencido())->toBeFalse();
});

it('apenas Financeiro (ou Admin) pode gerenciar pagamentos', function () {
    $financeiro = User::factory()->financeiro()->create();
    $admin = User::factory()->admin()->create();
    $solicitante = User::factory()->create();

    expect($financeiro->temPerfil(Perfil::Financeiro))->toBeTrue()
        ->and($financeiro->podeGerenciarPagamentos())->toBeTrue()
        ->and($admin->podeGerenciarPagamentos())->toBeTrue()
        ->and($solicitante->podeGerenciarPagamentos())->toBeFalse()
        ->and($solicitante->temPerfil(Perfil::Financeiro))->toBeFalse();
});

it('scope ativo do Banco filtra inativos', function () {
    Banco::factory()->create(['ativo' => true]);
    Banco::factory()->create(['ativo' => false]);

    expect(Banco::ativo()->count())->toBe(1);
});
