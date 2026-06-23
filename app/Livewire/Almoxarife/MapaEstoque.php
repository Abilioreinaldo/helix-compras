<?php

namespace App\Livewire\Almoxarife;

use App\Enums\Perfil;
use App\Models\EstoqueMinimo;
use App\Models\LoteEstoque;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mapa de Estoque — posição visual por item / lote / unidade, com status
 * (OK / Baixo / Vencido / Crítico). Leitura: reusa EstoqueMinimo::posicaoEstoquePara
 * (saldos vivos visíveis ao usuário, com mínimo) + lotes vivos para validade.
 */
class MapaEstoque extends Component
{
    use WithPagination;

    public string $filtroItem = '';

    public string $filtroUnidadeId = '';

    public string $filtroLote = '';

    public bool $apenasVencidos = false;

    public function mount(): void
    {
        abort_unless($this->autorizado(), 403);
    }

    private function autorizado(): bool
    {
        $usuario = auth()->user();

        return $usuario->is_admin || $usuario->temPerfil(Perfil::Almoxarife);
    }

    public function updatingFiltroItem(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroUnidadeId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroLote(): void
    {
        $this->resetPage();
    }

    public function updatingApenasVencidos(): void
    {
        $this->resetPage();
    }

    /**
     * Classifica o status do saldo (prioridade: crítico > vencido > baixo > ok).
     */
    private function classificar(float $saldo, ?float $minima, bool $temVencido): string
    {
        return match (true) {
            $saldo <= 0.0 => 'critico',
            $temVencido => 'vencido',
            $minima !== null && $saldo < $minima => 'baixo',
            default => 'ok',
        };
    }

    public function render(): View
    {
        abort_unless($this->autorizado(), 403);

        $posicao = EstoqueMinimo::posicaoEstoquePara(auth()->user());
        $saldoIds = $posicao->pluck('saldo_id')->all();

        // Lotes vivos por saldo (número + validade) — uma query só.
        $lotesPorSaldo = LoteEstoque::query()
            ->whereIn('saldo_estoque_id', $saldoIds)
            ->whereNull('fundido_para_id')
            ->get(['id', 'saldo_estoque_id', 'numero_lote', 'validade', 'quantidade'])
            ->groupBy('saldo_estoque_id');

        $hoje = Carbon::today();

        // Anexa lotes + status a cada linha.
        $linhas = $posicao->map(function (object $linha) use ($lotesPorSaldo, $hoje) {
            /** @var Collection<int, LoteEstoque> $lotes */
            $lotes = $lotesPorSaldo->get($linha->saldo_id, collect());
            $datados = $lotes->filter(fn (LoteEstoque $l) => $l->validade !== null);

            $saldo = (float) $linha->saldo_atual;
            $minima = $linha->quantidade_minima !== null ? (float) $linha->quantidade_minima : null;
            $temVencido = $datados->contains(fn (LoteEstoque $l) => $l->validade->lt($hoje));

            $linha->lotes = $lotes->sortBy('validade')->values();
            $linha->proxima_validade = $datados->sortBy('validade')->first()?->validade;
            $linha->status = $this->classificar($saldo, $minima, $temVencido);

            return $linha;
        });

        // Totais sobre a posição completa (KPIs estáveis, independem dos filtros).
        $totais = [
            'itens' => $linhas->count(),
            'baixos' => $linhas->where('status', 'baixo')->count(),
            'vencidos' => $linhas->where('status', 'vencido')->count(),
            'criticos' => $linhas->where('status', 'critico')->count(),
        ];

        // Opções de unidade a partir do que o usuário enxerga.
        $unidades = $linhas->map(fn (object $l) => ['id' => (int) $l->unidade_id, 'nome' => $l->unidade_nome])
            ->unique('id')->sortBy('nome')->values();

        // Filtros (em memória sobre a posição visível).
        $filtradas = $linhas
            ->when($this->filtroItem !== '', fn (Collection $c) => $c->filter(
                fn (object $l) => str_contains(mb_strtolower($l->descricao_item), mb_strtolower($this->filtroItem))
            ))
            ->when($this->filtroUnidadeId !== '', fn (Collection $c) => $c->filter(
                fn (object $l) => (int) $l->unidade_id === (int) $this->filtroUnidadeId
            ))
            ->when($this->filtroLote !== '', fn (Collection $c) => $c->filter(
                fn (object $l) => $l->lotes->contains(
                    fn (LoteEstoque $lote) => $lote->numero_lote !== null
                        && str_contains(mb_strtolower($lote->numero_lote), mb_strtolower($this->filtroLote))
                )
            ))
            ->when($this->apenasVencidos, fn (Collection $c) => $c->where('status', 'vencido'))
            ->values();

        // Paginação manual (a fonte é uma Collection, não um query builder).
        $pagina = $this->getPage();
        $porPagina = 20;
        $paginados = new LengthAwarePaginator(
            $filtradas->forPage($pagina, $porPagina)->values(),
            $filtradas->count(),
            $porPagina,
            $pagina,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        return view('livewire.almoxarife.mapa-estoque', [
            'linhas' => $paginados,
            'totais' => $totais,
            'unidades' => $unidades,
        ])->layout('components.layouts.app');
    }
}
