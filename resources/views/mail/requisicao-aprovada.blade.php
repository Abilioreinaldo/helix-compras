<x-mail::message>
# Requisição aprovada

Olá, {{ $requisicao->solicitante?->name ?? 'solicitante' }}.

Sua requisição **{{ $requisicao->codigo }}** foi **aprovada** e seguirá para a próxima etapa do processo.

**Unidade:** {{ $requisicao->unidade?->nome ?? '—' }}
**Aprovada em:** {{ $requisicao->aprovada_em?->format('d/m/Y H:i') ?? '—' }}

HELIX Compras
</x-mail::message>
