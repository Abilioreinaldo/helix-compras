<?php

namespace App\Livewire\Aprovacoes;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FilaAprovacoes extends Component
{
    use WithPagination;

    public string $filtroUnidadeId = '';

    public string $filtroFaixaId = '';

    /** Período em dias desde o início da aprovação ('' = todos). */
    public string $filtroPeriodo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);
    }

    public function updatingFiltroUnidadeId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroFaixaId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroPeriodo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);

        $usuario = auth()->user();

        // Pares (unidade_id, nivel_alcada) em que o usuário é Aprovador
        $pares = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Aprovador->value)
            ->whereNotNull('unidade_user.nivel_alcada')
            ->get()
            ->map(fn ($u) => [
                'unidade_id' => $u->id,
                'nivel' => $u->pivot->nivel_alcada instanceof NivelAlcada
                    ? $u->pivot->nivel_alcada->value
                    : $u->pivot->nivel_alcada,
            ]);

        $requisicoes = Requisicao::withoutGlobalScopes()
            ->with(['solicitante', 'unidade', 'faixaAlcada'])
            ->where('status', StatusRequisicao::AguardandoAprovacao->value)
            ->where(function ($q) use ($pares) {
                foreach ($pares as $par) {
                    $q->orWhere(function ($sub) use ($par) {
                        $sub->where('unidade_id', $par['unidade_id'])
                            ->whereHas('aprovacoes', fn ($a) => $a
                                ->where('status', StatusAprovacao::Pendente->value)
                                ->where('nivel_exigido', $par['nivel'])
                                ->whereColumn('ciclo', 'requisicoes.ciclo_aprovacao')
                            );
                    });
                }
            })
            ->when($this->filtroUnidadeId !== '', fn ($q) => $q->where('unidade_id', (int) $this->filtroUnidadeId))
            ->when($this->filtroFaixaId !== '', fn ($q) => $q->where('faixa_alcada_id', (int) $this->filtroFaixaId))
            ->when($this->filtroPeriodo !== '', fn ($q) => $q->where('aprovacao_iniciada_em', '>=', now()->subDays((int) $this->filtroPeriodo)))
            ->orderByDesc('updated_at')
            ->paginate(15);

        // Opções de filtro (apenas unidades em que o usuário aprova).
        $unidadesFiltro = Unidade::withoutGlobalScopes()
            ->whereIn('id', $pares->pluck('unidade_id')->unique()->values())
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $faixas = FaixaAlcada::orderBy('valor_minimo')->get(['id', 'nome']);

        return view('livewire.aprovacoes.fila-aprovacoes', compact('requisicoes', 'unidadesFiltro', 'faixas'))
            ->layout('components.layouts.app');
    }
}
