<?php

namespace App\Livewire\Compradora;

use App\Actions\ConcluirCotacaoAction;
use App\Actions\MarcarCotacaoVencedoraAction;
use App\Actions\RegistrarCotacaoAction;
use App\Enums\Perfil;
use App\Mail\SolicitacaoCotacao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
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

    /** @var array<int|string, string> preço unitário por item (item_requisicao_id => preço) */
    public array $precos = [];

    public string $prazoEntregaDias = '';

    public string $validadeProposta = '';

    public string $observacoes = '';

    /** @var TemporaryUploadedFile|null */
    public $arquivo = null;

    public bool $mostrarFormulario = false;

    public bool $mostrarModalConcluir = false;

    /** @var array<int, int> fornecedores selecionados para solicitar cotação por e-mail */
    public array $fornecedoresSolicitar = [];

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

        // Preços por item (matriz). Vazio => caminho legado por valor total.
        $precosPorItem = collect($this->precos)
            ->filter(fn ($v) => $v !== '' && $v !== null && is_numeric($v) && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->all();
        $usaItens = $precosPorItem !== [];

        $regras = [
            'fornecedorId' => 'required|exists:fornecedores,id',
            'prazoEntregaDias' => 'nullable|integer|min:1',
            'validadeProposta' => 'nullable|date',
            'observacoes' => 'nullable|string|max:1000',
            'arquivo' => 'nullable|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
        ];
        $regras[$usaItens ? 'precos.*' : 'valor'] = $usaItens ? 'nullable|numeric|min:0' : 'required|numeric|min:0.01';

        $this->validate($regras, [
            'fornecedorId.required' => 'Selecione um fornecedor.',
            'valor.required' => 'Informe o preço dos itens ou o valor total.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'arquivo.mimetypes' => 'O arquivo deve ser PDF, JPG ou PNG.',
            'arquivo.max' => 'O arquivo não pode ultrapassar 10 MB.',
        ]);

        $fornecedor = Fornecedor::findOrFail($this->fornecedorId);

        try {
            app(RegistrarCotacaoAction::class)->execute(
                $this->requisicao,
                $fornecedor,
                $usaItens ? 0.0 : (float) $this->valor,
                $this->arquivo,
                $this->prazoEntregaDias !== '' ? (int) $this->prazoEntregaDias : null,
                $this->observacoes ?: null,
                $this->validadeProposta ?: null,
                $usaItens ? $precosPorItem : null,
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

    /**
     * Envia a solicitação de cotação por e-mail aos fornecedores selecionados.
     * Cria uma cotação "aguardando" (valor null) por fornecedor — a captura IMAP depois
     * preenche a sugestão de valor/prazo, que a compradora confirma.
     */
    public function solicitarPorEmail(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $this->validate([
            'fornecedoresSolicitar' => 'required|array|min:1',
            'fornecedoresSolicitar.*' => 'integer|exists:fornecedores,id',
        ], [
            'fornecedoresSolicitar.required' => 'Selecione ao menos um fornecedor.',
        ]);

        $jaExistem = $this->requisicao->cotacoes()->whereNull('deleted_at')
            ->pluck('fornecedor_id')->map(fn ($id) => (int) $id)->all();

        $enviados = 0;
        foreach ($this->fornecedoresSolicitar as $fornecedorId) {
            if (in_array((int) $fornecedorId, $jaExistem, true)) {
                continue; // já há cotação para esse fornecedor nesta requisição
            }

            $fornecedor = Fornecedor::find($fornecedorId);
            if (! $fornecedor || ! $fornecedor->contato_email) {
                continue;
            }

            $cotacao = Cotacao::create([
                'requisicao_id' => $this->requisicao->id,
                'fornecedor_id' => $fornecedor->id,
                'valor' => null,
                'criada_por' => auth()->id(),
            ]);

            Mail::to($fornecedor->contato_email)->send(new SolicitacaoCotacao($cotacao));
            $enviados++;
        }

        $this->fornecedoresSolicitar = [];
        $this->requisicao->refresh();
        $this->requisicao->load(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada']);
        $this->dispatch('notify', mensagem: "Solicitação enviada a {$enviados} fornecedor(es).");
    }

    /** Confirma o valor oficial a partir da sugestão capturada por e-mail. */
    public function confirmarSugestao(int $cotacaoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::findOrFail($cotacaoId);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        if ($cotacao->valor_respondido === null) {
            $this->addError('formulario', 'Não há valor sugerido para confirmar.');

            return;
        }

        $cotacao->update([
            'valor' => $cotacao->valor_respondido,
            'prazo_entrega_dias' => $cotacao->prazo_respondido ?? $cotacao->prazo_entrega_dias,
        ]);

        $this->requisicao->refresh();
        $this->requisicao->load(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada']);
        $this->dispatch('notify', mensagem: 'Valor confirmado a partir da sugestão.');
    }

    public function marcarVencedora(int $cotacaoId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::with('fornecedor')->findOrFail($cotacaoId);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        if ($cotacao->valor === null) {
            $this->addError('cotacoes', 'Confirme o valor da cotação antes de marcá-la como vencedora.');

            return;
        }

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
        $this->precos = [];
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
        // Cotações "aguardando" (valor null, só com sugestão) não contam para o mínimo
        // até a compradora confirmar o valor.
        $confirmadas = $cotacoes->whereNotNull('valor')->count();
        $podeConcluir = $confirmadas >= $minimoNecessario && $temVencedora;

        return view('livewire.compradora.gestao-cotacoes', compact('fornecedores', 'minimoNecessario', 'cotacoes', 'temVencedora', 'podeConcluir'))
            ->layout('components.layouts.app');
    }
}
