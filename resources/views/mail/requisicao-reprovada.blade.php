<x-mail::message>
# Requisição reprovada

A requisição **{{ $requisicao->codigo }}** foi **reprovada** e retornou à fase de cotação.

**Reprovada por:** {{ $aprovador->name }}
**Justificativa:** {{ $justificativa }}
**Unidade:** {{ $requisicao->unidade?->nome ?? '—' }}

A compradora deverá revisar as cotações e reencaminhar para aprovação.

HELIX Compras
</x-mail::message>
