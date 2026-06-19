{{--
    Exibe a validade min/max dos lotes vivos de um saldo (Passo 6 v1.1-C).
    $v: objeto {min, max} (datas 'Y-m-d') ou null/ausente quando o saldo não tem lote datado.
    Vencido (min < hoje) destacado em vermelho — não bloqueia, é só alerta visual.
--}}
@if(!empty($v))
    @php
        $min = \Illuminate\Support\Carbon::parse($v->min);
        $max = \Illuminate\Support\Carbon::parse($v->max);
        $vencido = $min->lt(\Illuminate\Support\Carbon::today());
    @endphp
    <span class="{{ $vencido ? 'text-red-600 font-medium' : 'text-gray-600' }}" @if($vencido) title="Lote vencido" @endif>
        {{ $min->format('d/m/Y') }}@if($max->ne($min)) — {{ $max->format('d/m/Y') }}@endif
    </span>
@else
    <span class="text-gray-400">—</span>
@endif
