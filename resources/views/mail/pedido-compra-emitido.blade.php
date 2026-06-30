<x-mail::message>
# Pedido de Compra emitido

Olá, {{ $pedido->criador?->name ?? 'solicitante' }}.

Sua requisição foi incluída no **Pedido de Compra {{ $pedido->numero }}**, emitido em {{ $pedido->emitido_em?->format('d/m/Y H:i') ?? '—' }}.

**Fornecedor:** {{ $pedido->fornecedor?->razao_social ?? '—' }}
**Unidade:** {{ $pedido->unidade?->nome ?? '—' }}
@if($pedido->prazo_entrega)
**Prazo de Entrega:** {{ $pedido->prazo_entrega->format('d/m/Y') }}
@endif

HELIX Compras
</x-mail::message>
