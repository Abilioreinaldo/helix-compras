<x-mail::message>
# Proposta de cotação recebida

O fornecedor **{{ $cotacao->fornecedor?->nome ?? $cotacao->fornecedor?->razao_social ?? '—' }}** enviou a proposta da cotação **#COT-{{ $cotacao->id }}** pelo link de cotação.

@if($cotacao->valor_respondido !== null)
**Valor proposto:** R$ {{ number_format((float) $cotacao->valor_respondido, 2, ',', '.') }}
@endif
@if($cotacao->prazo_respondido !== null)
**Prazo proposto:** {{ $cotacao->prazo_respondido }} dias
@endif

Abra a cotação para conferir e **confirmar** o valor oficial.

HELIX Compras
</x-mail::message>
