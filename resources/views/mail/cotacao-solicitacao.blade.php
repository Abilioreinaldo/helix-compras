<x-mail::message>
# Solicitação de cotação

Olá, {{ $cotacao->fornecedor?->nome ?? $cotacao->fornecedor?->razao_social ?? 'fornecedor' }}.

A **HELIX Compras** gostaria de receber sua cotação para a {{ $cotacao->requisicao?->codigo ? 'requisição '.$cotacao->requisicao->codigo : 'requisição em aberto' }}.

**Como responder:** clique no botão abaixo e preencha os preços, o prazo de entrega e as observações da sua proposta.

<x-mail::button :url="$url">
Enviar proposta
</x-mail::button>

O link é **pessoal e de uso único** e vale até **{{ $expiraEm->format('d/m/Y H:i') }}**. Não o encaminhe.

Propostas enviadas como **resposta a este e-mail não são registradas** — use sempre o link.

Obrigado,
HELIX Compras
</x-mail::message>
