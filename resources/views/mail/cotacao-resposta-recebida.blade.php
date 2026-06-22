<x-mail::message>
# Resposta de cotação recebida

O fornecedor **{{ $cotacao->fornecedor?->nome_fantasia ?? $cotacao->fornecedor?->razao_social ?? '—' }}** respondeu à cotação **#COT-{{ $cotacao->id }}**.

@if($cotacao->valor_respondido !== null)
**Valor sugerido:** R$ {{ number_format((float) $cotacao->valor_respondido, 2, ',', '.') }}
@else
**Valor sugerido:** não identificado automaticamente — confira o e-mail e preencha manualmente.
@endif
@if($cotacao->prazo_respondido !== null)
**Prazo sugerido:** {{ $cotacao->prazo_respondido }} dias
@endif

Estes valores são **sugestões** extraídas do e-mail. Abra a cotação para conferir e **confirmar** o valor oficial.

{{ config('app.name') }}
</x-mail::message>
