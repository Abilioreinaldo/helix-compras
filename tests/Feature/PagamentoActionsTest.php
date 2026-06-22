<?php

use App\Actions\AgendarPagamentoAction;
use App\Actions\CancelarPagamentoAction;
use App\Actions\ProcessarReconciliacaoCsvAction;
use App\Actions\RegistrarPagamentoAction;
use App\Enums\MetodoPagamento;
use App\Enums\StatusPagamento;
use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function pa_pagamento(array $attrs = []): Pagamento
{
    return Pagamento::factory()->create(array_merge(['valor_total' => 1000, 'status' => StatusPagamento::Pendente], $attrs));
}

// ─── Registrar ───────────────────────────────────────────────────────────────

it('registra pagamento total → status pago', function () {
    $user = User::factory()->financeiro()->create();
    $pag = pa_pagamento();

    $res = app(RegistrarPagamentoAction::class)->execute($pag, 1000.00, now()->toDateString(), MetodoPagamento::Transferencia, null, 'NSU-1', null, $user);

    expect($res->status)->toBe(StatusPagamento::Pago)
        ->and((float) $res->valor_pago)->toBe(1000.00)
        ->and($res->atualizado_por)->toBe($user->id);
});

it('registra pagamento parcial → status parcial', function () {
    $user = User::factory()->financeiro()->create();
    $res = app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 400.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user);

    expect($res->status)->toBe(StatusPagamento::Parcial);
});

it('rejeita valor acima do total + 10%', function () {
    $user = User::factory()->financeiro()->create();
    expect(fn () => app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 1200.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user))
        ->toThrow(ValidationException::class);
});

it('aceita até a tolerância de 10% (juros/multa de última hora)', function () {
    $user = User::factory()->financeiro()->create();
    $res = app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 1100.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user);
    expect($res->status)->toBe(StatusPagamento::Pago);
});

it('status pago considera o total devido com desconto (calcularTotal, não valor_total)', function () {
    $user = User::factory()->financeiro()->create();
    $pag = pa_pagamento(['valor_total' => 1000, 'valor_desconto' => 100]); // devido = 900
    $res = app(RegistrarPagamentoAction::class)->execute($pag, 900.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user);
    expect($res->status)->toBe(StatusPagamento::Pago);
});

it('status parcial quando paga só o principal e há juros', function () {
    $user = User::factory()->financeiro()->create();
    $pag = pa_pagamento(['valor_total' => 1000, 'valor_juros' => 200]); // devido = 1200
    $res = app(RegistrarPagamentoAction::class)->execute($pag, 1000.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user);
    expect($res->status)->toBe(StatusPagamento::Parcial);
});

it('rejeita data de pagamento futura', function () {
    $user = User::factory()->financeiro()->create();
    expect(fn () => app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 100.00, now()->addDay()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user))
        ->toThrow(ValidationException::class);
});

it('exige número do cheque quando método é cheque', function () {
    $user = User::factory()->financeiro()->create();
    expect(fn () => app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 100.00, now()->toDateString(), MetodoPagamento::Cheque, null, null, null, $user))
        ->toThrow(ValidationException::class);

    $banco = Banco::factory()->create();
    $res = app(RegistrarPagamentoAction::class)->execute(pa_pagamento(), 100.00, now()->toDateString(), MetodoPagamento::Cheque, $banco, null, 'CHQ-9', $user);
    expect($res->numero_cheque)->toBe('CHQ-9');
});

it('não registra pagamento já pago', function () {
    $user = User::factory()->financeiro()->create();
    $pago = Pagamento::factory()->pago()->create();
    expect(fn () => app(RegistrarPagamentoAction::class)->execute($pago, 10.00, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $user))
        ->toThrow(ValidationException::class);
});

// ─── Agendar / Cancelar ──────────────────────────────────────────────────────

it('agenda pagamento para data futura', function () {
    $user = User::factory()->financeiro()->create();
    $res = app(AgendarPagamentoAction::class)->execute(pa_pagamento(), now()->addDays(5)->toDateString(), $user);
    expect($res->status)->toBe(StatusPagamento::Agendado)
        ->and($res->agendado_para->toDateString())->toBe(now()->addDays(5)->toDateString());
});

it('não agenda para o passado', function () {
    $user = User::factory()->financeiro()->create();
    expect(fn () => app(AgendarPagamentoAction::class)->execute(pa_pagamento(), now()->subDay()->toDateString(), $user))
        ->toThrow(ValidationException::class);
});

it('cancela pagamento em aberto com motivo', function () {
    $user = User::factory()->financeiro()->create();
    $res = app(CancelarPagamentoAction::class)->execute(pa_pagamento(), 'Duplicado', $user);
    expect($res->status)->toBe(StatusPagamento::Cancelado)
        ->and($res->observacoes)->toBe('Duplicado');
});

it('não cancela pagamento já pago', function () {
    $user = User::factory()->financeiro()->create();
    expect(fn () => app(CancelarPagamentoAction::class)->execute(Pagamento::factory()->pago()->create(), 'x', $user))
        ->toThrow(ValidationException::class);
});

// ─── Reconciliação CSV ───────────────────────────────────────────────────────

it('reconcilia: casa por referência e marca órfão sem match', function () {
    $user = User::factory()->financeiro()->create();
    $banco = Banco::factory()->create();
    pa_pagamento(['referencia_banco' => 'DOC123']);

    $csv = "DOC123;1.000,00;01/06/2026;Pagamento fornecedor\nDOC999;50,00;02/06/2026;Tarifa\n";
    $arquivo = UploadedFile::fake()->createWithContent('extrato.csv', $csv);

    $rec = app(ProcessarReconciliacaoCsvAction::class)->execute($arquivo, $banco, $user);

    expect($rec->total_linhas)->toBe(2)
        ->and((float) $rec->total_conciliado)->toBe(1000.00)
        ->and($rec->itens()->where('status', 'conciliado')->count())->toBe(1)
        ->and($rec->itens()->where('status', 'orfao')->count())->toBe(1);
});

it('não reprocessa o mesmo extrato (hash)', function () {
    $user = User::factory()->financeiro()->create();
    $banco = Banco::factory()->create();
    $csv = "DOC1;10,00;01/06/2026\n";

    app(ProcessarReconciliacaoCsvAction::class)->execute(UploadedFile::fake()->createWithContent('e.csv', $csv), $banco, $user);

    expect(fn () => app(ProcessarReconciliacaoCsvAction::class)->execute(UploadedFile::fake()->createWithContent('e.csv', $csv), $banco, $user))
        ->toThrow(ValidationException::class);
});
