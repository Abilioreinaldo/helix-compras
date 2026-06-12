<x-mail::message>
# Aprovação pendente

Olá, {{ $aprovador->name }}.

A requisição **{{ $requisicao->codigo }}** aguarda sua aprovação.

**Solicitante:** {{ $requisicao->solicitante?->name ?? '—' }}
**Unidade:** {{ $requisicao->unidade?->nome ?? '—' }}
**Valor estimado:** R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}

<x-mail::button :url="route('aprovacoes.painel', $requisicao)">
Revisar requisição
</x-mail::button>

Acesse o sistema para aprovar ou reprovar esta requisição.

{{ config('app.name') }}
</x-mail::message>
