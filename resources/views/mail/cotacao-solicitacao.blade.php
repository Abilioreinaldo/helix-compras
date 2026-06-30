<x-mail::message>
# Solicitação de cotação

Olá, {{ $cotacao->fornecedor?->nome_fantasia ?? $cotacao->fornecedor?->razao_social ?? 'fornecedor' }}.

A **HELIX Compras** gostaria de receber sua cotação para a {{ $cotacao->requisicao?->codigo ? 'requisição '.$cotacao->requisicao->codigo : 'requisição em aberto' }}.

**Como responder:**
Basta **responder este e-mail** (mantendo o assunto) informando:

- **Valor:** R$ XXX,XX
- **Prazo:** XX dias
- **Observações** (opcional)

Exemplo de resposta:

> Olá! Temos o preço de **R$ 145,00** com entrega em **12 dias úteis**. Item disponível em estoque.

Obrigado,
HELIX Compras
</x-mail::message>
