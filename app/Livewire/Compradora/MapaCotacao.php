<?php

namespace App\Livewire\Compradora;

use App\Actions\MarcarCotacaoVencedoraAction;
use App\Enums\Perfil;
use App\Models\Cotacao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Mapa de cotação: comparativo lado a lado dos fornecedores de uma requisição,
 * destacando a melhor compra (menor valor confirmado).
 *
 * Observação: cada Cotacao tem UM valor total por fornecedor (não há preço por item
 * no schema), então a comparação é por fornecedor — não uma matriz item × fornecedor.
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

    /** Cotações da requisição, da mais barata para a mais cara (nulos por último). */
    public function cotacoes()
    {
        return $this->requisicao->cotacoes()
            ->whereNull('deleted_at')
            ->with('fornecedor')
            ->get()
            ->sortBy(fn (Cotacao $c) => $c->valor ?? PHP_INT_MAX)
            ->values();
    }

    /** Id da cotação com o menor valor confirmado (melhor compra). */
    public function melhorCotacaoId(): ?int
    {
        return $this->cotacoes()
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
        $cotacoes = $this->cotacoes();
        $confirmadas = $cotacoes->filter(fn (Cotacao $c) => $c->valor !== null);

        $menor = $confirmadas->min('valor');
        $maior = $confirmadas->max('valor');
        $economia = ($menor !== null && $maior !== null) ? (float) $maior - (float) $menor : 0.0;

        return view('livewire.compradora.mapa-cotacao', [
            'cotacoes' => $cotacoes,
            'melhorId' => $this->melhorCotacaoId(),
            'menor' => $menor,
            'maior' => $maior,
            'economia' => $economia,
            'emCotacao' => $this->requisicao->status->value === 'em_cotacao',
        ])->layout('components.layouts.app');
    }
}
