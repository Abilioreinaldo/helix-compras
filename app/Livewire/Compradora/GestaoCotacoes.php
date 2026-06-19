<?php

namespace App\Livewire\Compradora;

use App\Actions\ConcluirCotacaoAction;
use App\Actions\MarcarCotacaoVencedoraAction;
use App\Actions\RegistrarCotacaoAction;
use App\Enums\Perfil;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class GestaoCotacoes extends Component
{
    use WithFileUploads;

    public Requisicao $requisicao;

    public ?int $fornecedorId = null;

    public string $valor = '';

    public string $prazoEntregaDias = '';

    public string $validadeProposta = '';

    public string $observacoes = '';

    /** @var TemporaryUploadedFile|null */
    public $arquivo = null;

    public bool $mostrarFormulario = false;

    public bool $mostrarModalConcluir = false;

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $this->requisicao = Requisicao::withoutGlobalScopes()
            ->with(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada'])
            ->findOrFail($id);

        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);
    }

    public function registrarCotacao(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $dados = $this->validate([
            'fornecedorId' => 'required|exists:fornecedores,id',
            'valor' => 'required|numeric|min:0.01',
            'prazoEntregaDias' => 'nullable|integer|min:1',
            'validadeProposta' => 'nullable|date',
            'observacoes' => 'nullable|string|max:1000',
            'arquivo' => 'nullable|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
        ], [
            'fornecedorId.required' => 'Selecione um fornecedor.',
            'valor.required' => 'Informe o valor da cotação.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'arquivo.mimetypes' => 'O arquivo deve ser PDF, JPG ou PNG.',
            'arquivo.max' => 'O arquivo não pode ultrapassar 10 MB.',
        ]);

        $fornecedor = Fornecedor::findOrFail($this->fornecedorId);

        try {
            app(RegistrarCotacaoAction::class)->execute(
                $this->requisicao,
                $fornecedor,
                (float) $this->valor,
                $this->arquivo,
                $this->prazoEntregaDias !== '' ? (int) $this->prazoEntregaDias : null,
                $this->observacoes ?: null,
                $this->validadeProposta ?: null
            );
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('formulario', $mensagem);

            return;
        }

        $this->resetForm();
        $this->requisicao->refresh();
        $this->requisicao->load(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada']);
        $this->dispatch('notify', mensagem: 'Cotação registrada com sucesso.');
    }

    public function marcarVencedora(int $cotacaoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::with('fornecedor')->findOrFail($cotacaoId);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        app(MarcarCotacaoVencedoraAction::class)->execute($this->requisicao, $cotacao);

        $this->requisicao->refresh();
        $this->requisicao->load(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada']);
        $this->dispatch('notify', mensagem: 'Cotação vencedora definida.');
    }

    public function concluirCotacao(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        try {
            app(ConcluirCotacaoAction::class)->execute($this->requisicao);
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('cotacoes', $mensagem);
            $this->mostrarModalConcluir = false;

            return;
        }

        $this->redirect(route('requisicoes.detalhe', $this->requisicao->id));
    }

    private function resetForm(): void
    {
        $this->fornecedorId = null;
        $this->valor = '';
        $this->prazoEntregaDias = '';
        $this->validadeProposta = '';
        $this->observacoes = '';
        $this->arquivo = null;
        $this->mostrarFormulario = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $fornecedores = Fornecedor::where('homologado', true)
            ->where('ativo', true)
            ->orderBy('nome_fantasia')
            ->get();

        $minimoNecessario = $this->requisicao->is_emergencial
            ? 1
            : ($this->requisicao->faixaAlcada?->minimo_cotacoes ?? 3);

        $cotacoes = $this->requisicao->cotacoes()->whereNull('deleted_at')->with('fornecedor', 'criador')->get();
        $temVencedora = $cotacoes->where('vencedora', true)->count() === 1;
        $podeConcluir = $cotacoes->count() >= $minimoNecessario && $temVencedora;

        return view('livewire.compradora.gestao-cotacoes', compact('fornecedores', 'minimoNecessario', 'cotacoes', 'temVencedora', 'podeConcluir'))
            ->layout('components.layouts.app');
    }
}
