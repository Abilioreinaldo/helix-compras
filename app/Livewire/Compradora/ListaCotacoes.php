<?php

namespace App\Livewire\Compradora;

use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Visão geral da fase de Cotação (menu próprio). Lista as requisições que estão em
 * cotação, com o progresso por requisição (fornecedores contatados, situação).
 *
 * O detalhe/gestão de cada cotação continua em GestaoCotacoes (/compradora/cotacoes/{id}).
 * A "situação" é DERIVADA — Cotacao não tem coluna status.
 */
class ListaCotacoes extends Component
{
    use WithPagination;

    /** Filtro por status da requisição na fase de cotação. */
    public string $filtroStatus = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
    }

    public function updatingFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $statusCotacao = [StatusRequisicao::EmCotacao->value, StatusRequisicao::CotacaoConcluida->value];
        $filtro = in_array($this->filtroStatus, $statusCotacao, true) ? $this->filtroStatus : null;

        $requisicoes = Requisicao::query()
            ->whereIn('status', $filtro ? [$filtro] : $statusCotacao)
            ->with(['unidade', 'faixaAlcada'])
            ->withCount([
                'itens',
                'cotacoes',
                'cotacoes as cotacoes_confirmadas_count' => fn ($q) => $q->whereNotNull('valor'),
                'cotacoes as cotacoes_vencedoras_count' => fn ($q) => $q->where('vencedora', true),
            ])
            ->latest('updated_at')
            ->paginate(20);

        return view('livewire.compradora.lista-cotacoes', ['requisicoes' => $requisicoes])
            ->layout('components.layouts.app');
    }
}
