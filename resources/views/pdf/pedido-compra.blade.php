<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $pedido->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 20px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .header { border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 16px; }
        .grid-2 { display: table; width: 100%; margin-bottom: 12px; }
        .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11px; }
        th { background: #f0f0f0; padding: 6px 8px; text-align: left; border: 1px solid #ccc; }
        td { padding: 5px 8px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .subtotal { background: #f9f9f9; font-weight: bold; }
        .total-row { background: #e8e8e8; font-weight: bold; font-size: 12px; }
        .rastreabilidade { font-size: 10px; color: #555; border-top: 1px solid #ccc; margin-top: 16px; padding-top: 8px; }
        .badge { display: inline-block; background: #1a1a1a; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
    </style>
</head>
<body>

<div class="header">
    <h1>REDE COMENDADOR</h1>
    <div style="display: table; width: 100%">
        <div style="display: table-cell; vertical-align: top;">
            <p style="margin:0;font-size:11px;">Pedido de Compra</p>
            <p style="margin:4px 0 0;font-size:20px;font-weight:bold;font-family:monospace;">{{ $pedido->numero }}</p>
        </div>
        <div style="display: table-cell; vertical-align: top; text-align: right; font-size: 11px;">
            <p style="margin:0"><strong>Emitido em:</strong> {{ $pedido->emitido_em?->format('d/m/Y H:i') }}</p>
            <p style="margin:2px 0"><strong>Emitido por:</strong> {{ $pedido->emissor?->name }}</p>
            <p style="margin:0"><strong>Unidade:</strong> {{ $pedido->unidade?->nome }}</p>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="col">
        <h2>Fornecedor</h2>
        <p style="margin:0"><strong>{{ $pedido->fornecedor->razao_social }}</strong></p>
        <p style="margin:2px 0;font-size:11px;">CNPJ: {{ $pedido->fornecedor->cnpj }}</p>
        @if($pedido->fornecedor->contato_nome)
        <p style="margin:2px 0;font-size:11px;">Contato: {{ $pedido->fornecedor->contato_nome }}</p>
        @endif
    </div>
    <div class="col">
        <h2>Condições</h2>
        @if($pedido->prazo_entrega)
        <p style="margin:0 0 2px;font-size:11px;"><strong>Prazo de Entrega:</strong> {{ $pedido->prazo_entrega->format('d/m/Y') }}</p>
        @endif
        @if($pedido->modalidade_entrega)
        <p style="margin:0 0 2px;font-size:11px;"><strong>Modalidade:</strong> {{ $pedido->modalidade_entrega->label() }}</p>
        @endif
        @if($pedido->condicoes_pagamento)
        <p style="margin:0;font-size:11px;"><strong>Pagamento:</strong> {{ $pedido->condicoes_pagamento }}</p>
        @endif
        @if(! $pedido->prazo_entrega && ! $pedido->modalidade_entrega && ! $pedido->condicoes_pagamento)
        <p style="margin:0;font-size:11px;color:#999;">Não informado</p>
        @endif
    </div>
</div>

<h2>Itens do Pedido</h2>

@foreach($itensPorDestino as $destino => $itensDest)
<p style="margin:8px 0 4px;font-size:11px;"><strong>Destino: {{ $destino }}</strong></p>
<table>
    <thead>
        <tr>
            <th style="width:40%">Descrição</th>
            <th class="text-right" style="width:8%">Qtd</th>
            <th style="width:6%">Un</th>
            <th class="text-right" style="width:15%">Valor Unit.</th>
            <th class="text-right" style="width:15%">Total</th>
            <th style="width:16%">Requisição</th>
        </tr>
    </thead>
    <tbody>
        @php $subtotal = 0; @endphp
        @foreach($itensDest as $item)
        @php $subtotal += (float)$item->valor_total; @endphp
        <tr>
            <td>{{ $item->descricao }}</td>
            <td class="text-right">{{ number_format((float)$item->quantidade, 2, ',', '.') }}</td>
            <td>{{ $item->unidade_medida }}</td>
            <td class="text-right">R$ {{ number_format((float)$item->valor_unitario, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format((float)$item->valor_total, 2, ',', '.') }}</td>
            <td style="font-size:10px;">{{ $item->requisicao?->codigo }}</td>
        </tr>
        @endforeach
        <tr class="subtotal">
            <td colspan="4" class="text-right">Subtotal {{ $destino }}</td>
            <td class="text-right">R$ {{ number_format($subtotal, 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
@endforeach

<table>
    <tr class="total-row">
        <td style="width:80%" class="text-right"><strong>TOTAL GERAL</strong></td>
        <td class="text-right"><strong>R$ {{ number_format($pedido->itens->sum(fn($i) => (float)$i->valor_total), 2, ',', '.') }}</strong></td>
        <td></td>
    </tr>
</table>

<div class="rastreabilidade">
    <strong>Rastreabilidade</strong><br>
    @foreach($pedido->itens->pluck('requisicao_id')->unique() as $reqId)
        @php $req = $pedido->itens->where('requisicao_id', $reqId)->first()?->requisicao; @endphp
        @if($req)
        <p style="margin:4px 0">
            Ref. {{ $req->codigo }} — Solicitante: {{ $req->solicitante->name }}
            @if(isset($aprovadores[$reqId]))
                — Aprovada por {{ $aprovadores[$reqId]->aprovador->name }} em {{ $aprovadores[$reqId]->decidida_em->format('d/m/Y') }}
            @elseif($req->aprovada_em)
                — Aprovada em {{ $req->aprovada_em->format('d/m/Y') }}
            @endif
        </p>
        @endif
    @endforeach
</div>

</body>
</html>
