<x-mail::message>
# Lembrete — aprovação pendente há mais de 48h

Olá, {{ $aprovador->name }}.

A requisição **{{ $requisicao->codigo }}** está aguardando sua aprovação há **mais de 48 horas**.

**Solicitante:** {{ $requisicao->solicitante?->name ?? '—' }}
**Unidade:** {{ $requisicao->unidade?->nome ?? '—' }}
**Aguardando desde:** {{ $requisicao->aprovacao_iniciada_em?->format('d/m/Y H:i') ?? '—' }}
**Valor estimado:** R$ {{ number_format($requisicao->valorTotal(), 2, ',', '.') }}

<x-mail::button :url="route('aprovacoes.painel', $requisicao)">
Revisar requisição
</x-mail::button>

Por favor, revise esta pendência o quanto antes.

HELIX Compras
</x-mail::message>
