<?php

use App\Services\ParseadorRespostaEmailService as Parser;

// ─── Valor ───────────────────────────────────────────────────────────────────

it('extrai valor simples R$ 150,00', function () {
    expect(Parser::extrairValor('Valor: R$ 150,00'))->toBe(150.00);
});

it('extrai valor com separador de milhar', function () {
    expect(Parser::extrairValor('Total: R$ 1.500,50'))->toBe(1500.50);
});

it('extrai valor sem centavos', function () {
    expect(Parser::extrairValor('Temos por R$ 150 à vista'))->toBe(150.00);
});

it('extrai valor é case-insensitive', function () {
    expect(Parser::extrairValor('valor: r$ 100,00'))->toBe(100.00)
        ->and(Parser::extrairValor('VALOR: R$ 100,00'))->toBe(100.00);
});

it('extrai valor por rótulo Preço', function () {
    expect(Parser::extrairValor('Preço: R$ 89,90'))->toBe(89.90);
});

it('extrai valor com milhar sem vírgula (1.500 = 1500)', function () {
    expect(Parser::extrairValor('R$ 1.500'))->toBe(1500.00);
});

it('retorna null quando não há valor', function () {
    expect(Parser::extrairValor('Bom dia, podemos atender o pedido.'))->toBeNull();
});

// ─── Prazo ───────────────────────────────────────────────────────────────────

it('extrai prazo Entrega: 15 dias', function () {
    expect(Parser::extrairPrazo('Entrega: 15 dias'))->toBe(15);
});

it('extrai prazo Prazo: 7 dias', function () {
    expect(Parser::extrairPrazo('Prazo: 7 dias'))->toBe(7);
});

it('extrai prazo em dias úteis', function () {
    expect(Parser::extrairPrazo('Entregamos em 12 dias úteis'))->toBe(12);
});

it('extrai prazo solto "10 dias"', function () {
    expect(Parser::extrairPrazo('Conseguimos em 10 dias'))->toBe(10);
});

it('retorna null quando não há prazo', function () {
    expect(Parser::extrairPrazo('Valor R$ 50,00 apenas'))->toBeNull();
});

// ─── Combinado (caso real) ───────────────────────────────────────────────────

it('extrai valor e prazo de uma resposta real', function () {
    $corpo = 'Olá! Temos o preço de R$ 145,00 com entrega em 12 dias úteis. Item em estoque.';
    expect(Parser::extrairValor($corpo))->toBe(145.00)
        ->and(Parser::extrairPrazo($corpo))->toBe(12);
});
