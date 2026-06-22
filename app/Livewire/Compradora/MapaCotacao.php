<?php

namespace App\Livewire\Compradora;

use App\Actions\MarcarCotacaoVencedoraAction;
use App\Enums\Perfil;
use App\Models\Cotacao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Mapa de cotação: matriz Item × Fornecedor de uma requisição, com o menor preço por
 * item (⭐) e o menor total (💚).
 *
 * Cotações com preço por item (itens_cotacao) preenchem as células; cotações só-total
 * (legado / confirmadas via IMAP) mostram apenas o total da coluna.
 */
class MapaCotacao extends Component
{
    public Requisicao $requisicao;

    public function mount(int $requisicaoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->requisicao = Requisicao::withoutGlobalScopes()
            ->with(['unidade', 'itens'])
            ->findOrFail($requisicaoId);
    }

    /** Cotações da requisição (colunas), da mais barata para a mais cara. */
    public function cotacoes(): Collection
    {
        // SoftDeletes já exclui apagadas. Carrega itemRequisicao para valorLinha() (evita N+1).
        return $this->requisicao->cotacoes()
            ->with(['fornecedor', 'itensCotacao.itemRequisicao'])
            ->get()
            ->sortBy(fn (Cotacao $c) => $c->valor ?? PHP_INT_MAX)
            ->values();
    }

    /** Id da cotação com o menor TOTAL confirmado (melhor compra geral). */
    public function melhorCotacaoId(?Collection $cotacoes = null): ?int
    {
        return ($cotacoes ?? $this->cotacoes())
            ->filter(fn (Cotacao $c) => $c->valor !== null)
            ->sortBy('valor')
            ->first()?->id;
    }

    public function temCotacaoConfirmada(): bool
    {
        return $this->cotacoes()->contains(fn (Cotacao $c) => $c->valor !== null);
    }

    public function marcarVencedora(int $cotacaoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::with('fornecedor')->findOrFail($cotacaoId);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        if ($cotacao->valor === null) {
            $this->addError('mapa', 'Confirme o valor da cotação antes de marcá-la como vencedora.');

            return;
        }

        app(MarcarCotacaoVencedoraAction::class)->execute($this->requisicao, $cotacao);
        $this->dispatch('notify', mensagem: 'Cotação vencedora definida.');
    }

    public function render(): View
    {
        $itens = $this->requisicao->itens;
        $cotacoes = $this->cotacoes();

        // valor da linha (unitário × quantidade) por cotação e item.
        $precoLinha = []; // [cotacaoId][itemRequisicaoId] => float
        foreach ($cotacoes as $c) {
            foreach ($c->itensCotacao as $ic) {
                $precoLinha[$c->id][$ic->item_requisicao_id] = $ic->valorLinha();
            }
        }

        // melhor (menor) preço por item.
        $melhorPorItem = []; // itemId => ['cotacao_id' => int, 'valor' => float]
        foreach ($itens as $item) {
            $menor = null;
            $melhorCot = null;
            foreach ($cotacoes as $c) {
                $v = $precoLinha[$c->id][$item->id] ?? null;
                if ($v !== null && ($menor === null || $v < $menor)) {
                    $menor = $v;
                    $melhorCot = $c->id;
                }
            }
            $melhorPorItem[$item->id] = $melhorCot !== null ? ['cotacao_id' => $melhorCot, 'valor' => $menor] : null;
        }

        return view('livewire.compradora.mapa-cotacao', [
            'itens' => $itens,
            'cotacoes' => $cotacoes,
            'precoLinha' => $precoLinha,
            'melhorPorItem' => $melhorPorItem,
            'melhorTotalId' => $this->melhorCotacaoId($cotacoes),
            'emCotacao' => $this->requisicao->status->value === 'em_cotacao',
        ])->layout('components.layouts.app');
    }
}
