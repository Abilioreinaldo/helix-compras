<x-mail::message>
# Pedido de Compra recebido

Olá, {{ $pedido->criador?->name ?? 'solicitante' }}.

Sua requisição vinculada ao **Pedido de Compra {{ $pedido->numero }}** foi recebida com sucesso e a requisição foi concluída.

**Fornecedor:** {{ $pedido->fornecedor?->razao_social ?? '—' }}
**Unidade:** {{ $pedido->unidade?->nome ?? '—' }}

{{ config('app.name') }}
</x-mail::message>
