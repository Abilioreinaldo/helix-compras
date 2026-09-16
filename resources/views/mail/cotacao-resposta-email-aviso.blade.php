<x-mail::message>
# Resposta recebida por e-mail — conferir

Chegou um e-mail de resposta referente à cotação **#COT-{{ $cotacao->id }}** ({{ $cotacao->fornecedor?->nome_fantasia ?? $cotacao->fornecedor?->razao_social ?? '—' }}).

**Nenhum valor foi registrado.** Propostas só são gravadas pelo link de cotação enviado ao fornecedor. Confira a mensagem na caixa de cotações e, se for o caso, reenvie o link.

- **Remetente:** {{ $remetente }} {{ $remetenteConfere ? '(confere com o cadastro do fornecedor)' : '(NÃO confere com o cadastro do fornecedor)' }}
- **Autenticidade (SPF/DKIM/DMARC):** {{ $autenticado ? 'verificada' : 'NÃO verificada — trate com cautela' }}

HELIX Compras
</x-mail::message>
