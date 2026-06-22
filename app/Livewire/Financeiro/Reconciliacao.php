<?php

namespace App\Livewire\Financeiro;

use App\Actions\ProcessarReconciliacaoCsvAction;
use App\Models\Banco;
use App\Models\ReconciliacaoBancaria;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Reconciliacao extends Component
{
    use WithFileUploads;

    public string $bancoId = '';

    /** @var TemporaryUploadedFile|null */
    public $arquivo = null;

    #[Locked]
    public ?int $reconciliacaoId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
    }

    public function processar(): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);

        $this->validate([
            'bancoId' => 'required|exists:bancos,id',
            'arquivo' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'bancoId.required' => 'Selecione o banco.',
            'arquivo.required' => 'Envie o arquivo do extrato (CSV).',
            'arquivo.mimes' => 'O arquivo deve ser CSV ou TXT.',
            'arquivo.max' => 'O arquivo não pode ultrapassar 5 MB.',
        ]);

        try {
            $rec = app(ProcessarReconciliacaoCsvAction::class)->execute(
                $this->arquivo,
                Banco::findOrFail($this->bancoId),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('formulario', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->reconciliacaoId = $rec->id;
        $this->arquivo = null;
        $this->dispatch('notify', mensagem: 'Extrato processado.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);

        $reconciliacao = $this->reconciliacaoId !== null
            ? ReconciliacaoBancaria::with(['itens.pagamento.fornecedor', 'banco'])->find($this->reconciliacaoId)
            : null;

        return view('livewire.financeiro.reconciliacao', [
            'bancos' => Banco::ativo()->orderBy('nome')->get(),
            'reconciliacao' => $reconciliacao,
        ])->layout('components.layouts.app');
    }
}
