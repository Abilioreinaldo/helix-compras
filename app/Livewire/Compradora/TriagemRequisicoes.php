<?php

namespace App\Livewire\Compradora;

use App\Actions\SaidaEstoqueAction;
use App\Actions\TransicionarStatusRequisicaoAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\LoteEstoque;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class TriagemRequisicoes extends Component
{
    use WithPagination;

    public string $observacaoDevolucao = '';

    public ?int $devolvendo = null;

    public string $erroAtendimentoEstoque = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
    }

    public function iniciarTriagem(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($id);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::EmTriagem);
        $this->dispatch('notify', mensagem: 'Triagem iniciada.');
    }

    public function enviarParaCotacao(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($id);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::EmCotacao);
        $this->dispatch('notify', mensagem: 'Requisição enviada para cotação.');
    }

    public function abrirDevolucao(int $id): void
    {
        $this->devolvendo = $id;
        $this->observacaoDevolucao = '';
    }

    public function confirmarDevolucao(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->validate(['observacaoDevolucao' => 'required|string|min:5'], [
            'observacaoDevolucao.required' => 'Informe o motivo da devolução.',
        ]);

        $requisicao = Requisicao::withoutGlobalScopes()->findOrFail($this->devolvendo);
        app(TransicionarStatusRequisicaoAction::class)->execute($requisicao, StatusRequisicao::Devolvida, $this->observacaoDevolucao);

        $this->devolvendo = null;
        $this->observacaoDevolucao = '';
        $this->dispatch('notify', mensagem: 'Requisição devolvida ao solicitante.');
    }

    /**
     * Verifica se todos os itens da requisição têm saldo disponível na unidade de destino.
     * Retorna false se houver algum item avulso ou sem saldo suficiente.
     */
    public function todosItensTemSaldo(Requisicao $requisicao): bool
    {
        $itens = $requisicao->itens;

        if ($itens->isEmpty()) {
            return false;
        }

        foreach ($itens as $item) {
            // Item avulso (sem catálogo) não pode ser atendido diretamente
            if ($item->avulso || ! $item->item_catalogo_id) {
                return false;
            }

            // Verifica saldo na unidade da requisição por item_catalogo_id
            $saldo = SaldoEstoque::where('unidade_id', $requisicao->unidade_id)
                ->where('item_catalogo_id', $item->item_catalogo_id)
                ->whereNull('fundido_para_id')
                ->first();

            if (! $saldo || (float) $saldo->quantidade < (float) $item->quantidade) {
                return false;
            }
        }

        return true;
    }

    /**
     * Indica se o atendimento direto desta requisição debitaria algum lote VENCIDO
     * (item controla_lote com lote vivo de validade < hoje no saldo da unidade).
     * Apenas alerta visual — não impede o atendimento.
     */
    public function temLoteVencido(Requisicao $requisicao): bool
    {
        $saldoIds = $requisicao->itens
            ->filter(fn ($item) => $item->item_catalogo_id)
            ->map(fn ($item) => SaldoEstoque::where('unidade_id', $requisicao->unidade_id)
                ->where('item_catalogo_id', $item->item_catalogo_id)
                ->whereNull('fundido_para_id')
                ->value('id'))
            ->filter();

        return LoteEstoque::saldosComLoteVencido($saldoIds)->isNotEmpty();
    }

    public function atenderDoEstoque(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->erroAtendimentoEstoque = '';
        $requisicao = Requisicao::withoutGlobalScopes()->with('itens')->findOrFail($id);
        $compradora = auth()->user();

        // Validação prévia: nenhum item avulso
        foreach ($requisicao->itens as $item) {
            if ($item->avulso || ! $item->item_catalogo_id) {
                $this->erroAtendimentoEstoque = 'Requisição contém item avulso. Atendimento direto não permitido.';

                return;
            }
        }

        try {
            DB::transaction(function () use ($requisicao, $compradora) {
                foreach ($requisicao->itens as $item) {
                    $saldo = SaldoEstoque::where('unidade_id', $requisicao->unidade_id)
                        ->where('item_catalogo_id', $item->item_catalogo_id)
                        ->whereNull('fundido_para_id')
                        ->lockForUpdate()
                        ->first();

                    if (! $saldo) {
                        throw ValidationException::withMessages([
                            'saldo' => "Saldo não encontrado para o item: {$item->descricao}.",
                        ]);
                    }

                    app(SaidaEstoqueAction::class)->execute(
                        $saldo,
                        (float) $item->quantidade,
                        "Atendimento direto REQ#{$requisicao->id}: {$item->descricao}",
                        $compradora,
                        atendimentoDireto: true,
                    );
                }

                app(TransicionarStatusRequisicaoAction::class)->execute(
                    $requisicao,
                    StatusRequisicao::Concluida,
                    'Atendido diretamente do estoque pela compradora.',
                    false,
                );
            });

            $this->dispatch('notify', mensagem: "Requisição #{$requisicao->id} concluída via estoque.");
        } catch (ValidationException $e) {
            $this->erroAtendimentoEstoque = collect($e->errors())->flatten()->first()
                ?? 'Erro ao atender do estoque.';
        }
    }

    public function render(): View
    {
        $requisicoes = Requisicao::withoutGlobalScopes()
            ->with(['solicitante', 'unidade', 'centroCusto', 'itens'])
            ->whereIn('status', [StatusRequisicao::AguardandoTriagem->value, StatusRequisicao::EmTriagem->value])
            ->orderByRaw('CASE WHEN atrasada = 1 THEN 0 ELSE 1 END')
            ->orderBy('submetida_em')
            ->paginate(15);

        return view('livewire.compradora.triagem-requisicoes', compact('requisicoes'))
            ->layout('components.layouts.app');
    }
}
