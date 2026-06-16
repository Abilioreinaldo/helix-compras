<?php

namespace App\Livewire\Compradora;

use App\Models\EstoqueMinimo;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ItensARepor extends Component
{
    public string $busca = '';

    public string $filtroUnidadeId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
    }

    /**
     * Redireciona para o formulário de requisição pré-preenchido com o item e quantidade sugerida.
     */
    public function solicitarReposicao(int $unidadeId, int $itemCatalogoId, float $quantidadeSugerida): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Só permite reposição de uma combinação (unidade, item) que está REALMENTE em alerta
        // na visão do usuário — evita redirect com parâmetros forjados.
        $emAlerta = EstoqueMinimo::itensAReporPara(auth()->user())
            ->contains(fn ($item) => (int) $item->unidade_id === $unidadeId
                && (int) $item->item_catalogo_id === $itemCatalogoId);

        abort_unless($emAlerta, 404);

        $this->redirect(
            route('requisicoes.criar', [
                'item_catalogo_id' => $itemCatalogoId,
                'unidade_id' => $unidadeId,
                'quantidade_sugerida' => max(0.0, $quantidadeSugerida),
            ])
        );
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        $usuario = auth()->user();

        $todosItensARepor = EstoqueMinimo::itensAReporPara($usuario);

        // Filtro por unidade
        if ($this->filtroUnidadeId !== '') {
            $todosItensARepor = $todosItensARepor->filter(
                fn ($item) => (int) $item->unidade_id === (int) $this->filtroUnidadeId
            )->values();
        }

        // Filtro por busca (descrição do item)
        if ($this->busca !== '') {
            $busca = mb_strtolower($this->busca);
            $todosItensARepor = $todosItensARepor->filter(
                fn ($item) => str_contains(mb_strtolower($item->item_descricao), $busca)
            )->values();
        }

        // Agrupar por unidade
        $itensPorUnidade = $todosItensARepor->groupBy('unidade_id');

        // Lista de unidades para o filtro (todas ativas sem soft-delete)
        $unidades = Unidade::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->pluck('nome', 'id');

        return view('livewire.compradora.itens-a-repor', compact('itensPorUnidade', 'unidades'))
            ->layout('components.layouts.app');
    }
}
