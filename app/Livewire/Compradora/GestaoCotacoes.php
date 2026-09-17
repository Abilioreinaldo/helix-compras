<?php

namespace App\Livewire\Compradora;

use App\Actions\ConcluirCotacaoAction;
use App\Actions\MarcarCotacaoVencedoraAction;
use App\Actions\RegistrarCotacaoAction;
use App\Mail\SolicitacaoCotacao;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Services\CotacaoLinkService;
use Helix\Foundation\Exceptions\ChannelException;
use Helix\Foundation\Livewire\Concerns\AuthorizesOnHydrate;
use Helix\Foundation\Models\Platform\Channels\TenantChannelCredential;
use Helix\Foundation\Services\Platform\Channels\CommercialMessenger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class GestaoCotacoes extends Component
{
    use AuthorizesOnHydrate;
    use WithFileUploads;

    // Locked: a requisição em cotação é fixada no mount; o cliente não a reaponta.
    #[Locked]
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

    /** Prazo de resposta da solicitação (Y-m-d): vira o expires_at do link assinado. */
    public string $prazoResposta = '';

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $this->requisicao = Requisicao::withoutGlobalScope(UnidadeScope::class)
            ->with(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada'])
            ->findOrFail($id);

        $this->autorizarRequisicao();

        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $this->prazoResposta = now()->addDays(7)->toDateString();
    }

    /**
     * SEGUNDA TRANCA (2ª auditoria adversarial): a requisição é carregada com
     * `withoutGlobalScope(UnidadeScope)` e depois VIVE como propriedade do componente.
     * Model em propriedade Livewire é reidratado por `newQueryForRestoration()` →
     * `newQueryWithoutScopes()`, que NÃO aplica global scope nenhum: o snapshot é o
     * caminho que dispensa a consulta escopada. Desde a fundação v0.4.0 o synth barra
     * o registro de OUTRO tenant; a posse dentro do tenant (unidade/vínculo) é da
     * policy `operar` — no mount (autorizarRequisicao) E em toda requisição
     * subsequente (AuthorizesOnHydrate, com a habilidade abaixo).
     */
    protected function hydrationAbility(): string
    {
        return 'operar';
    }

    private function autorizarRequisicao(): void
    {
        abort_unless(auth()->user()?->can('operar', $this->requisicao), 403);
    }

    public function registrarCotacao(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        // Preços por item (matriz). Vazio => caminho legado por valor total.
        $precosPorItem = collect($this->precos)
            ->filter(fn ($v) => $v !== '' && $v !== null && is_numeric($v) && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->all();
        $usaItens = $precosPorItem !== [];

        $regras = [
            // FK validada POR TENANT: fornecedor de outro tenant é "inexistente" aqui.
            'fornecedorId' => ['required', Rule::exists('fornecedores', 'id')->where('tenant_id', $this->requisicao->tenant_id)->whereNull('deleted_at')],
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
        abort_unless(auth()->user()->can('operar', $fornecedor), 403);

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
     * Envia a solicitação de cotação aos fornecedores selecionados (decisão 11).
     * Cria uma cotação "aguardando" (valor null) por fornecedor e envia o LINK ASSINADO
     * de uso único, válido até o prazo de resposta — o único canal que grava a proposta.
     * Fornecedor que já tem cotação AGUARDANDO nesta requisição recebe link novo (o
     * anterior é revogado): é assim que cotações abertas antes do link migram.
     */
    public function solicitarPorEmail(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $this->validate([
            'fornecedoresSolicitar' => 'required|array|min:1',
            'fornecedoresSolicitar.*' => ['integer', Rule::existsInTenant('fornecedores')],
            'prazoResposta' => $this->regraPrazoResposta(),
        ], [
            'fornecedoresSolicitar.required' => 'Selecione ao menos um fornecedor.',
            'prazoResposta.*' => 'Informe um prazo de resposta entre hoje e 90 dias.',
        ]);

        if ($this->semCanalDeEmail()) {
            return;
        }

        $existentes = $this->requisicao->cotacoes()->whereNull('deleted_at')->get()->keyBy('fornecedor_id');

        $enviados = 0;
        foreach ($this->fornecedoresSolicitar as $fornecedorId) {
            $fornecedor = Fornecedor::find($fornecedorId);
            if (! $fornecedor || ! $fornecedor->contato_email) {
                continue;
            }

            $cotacao = $existentes->get($fornecedor->id);
            if ($cotacao !== null && $cotacao->valor !== null) {
                continue; // cotação já confirmada: não há proposta a pedir
            }

            $cotacao ??= Cotacao::create([
                'requisicao_id' => $this->requisicao->id,
                'fornecedor_id' => $fornecedor->id,
                'valor' => null,
                'criada_por' => auth()->id(),
            ]);

            try {
                $this->enviarLink($cotacao);
            } catch (ChannelException) {
                // Canal suspenso ENTRE a conferência e o envio (ou cota do minuto
                // estourada): para aqui em vez de seguir emitindo links que ninguém
                // recebe. O que já saiu, saiu; o resto o comprador reenvia depois.
                $this->erroDeCanal();
                break;
            }

            $enviados++;
        }

        $this->fornecedoresSolicitar = [];
        $this->recarregar();
        $this->dispatch('notify', mensagem: "Solicitação enviada a {$enviados} fornecedor(es).");
    }

    /** Reemite o link de uma cotação aguardando (revoga o anterior) e reenvia o e-mail. */
    public function reenviarLink(int $cotacaoId): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::with('fornecedor')->findOrFail($cotacaoId);
        abort_unless(auth()->user()->can('operar', $cotacao), 403);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        $this->validate(['prazoResposta' => $this->regraPrazoResposta()], [
            'prazoResposta.*' => 'Informe um prazo de resposta entre hoje e 90 dias.',
        ]);

        if ($cotacao->valor !== null || ! $cotacao->fornecedor?->contato_email) {
            $this->addError('cotacoes', 'Só é possível reenviar o link de cotação aguardando, de fornecedor com e-mail.');

            return;
        }

        if ($this->semCanalDeEmail()) {
            return;
        }

        $this->enviarLink($cotacao);
        $this->recarregar();
        $this->dispatch('notify', mensagem: 'Link de cotação reenviado.');
    }

    /** Revoga o link ainda utilizável da cotação (o fornecedor passa a ver "link indisponível"). */
    public function revogarLink(int $cotacaoId): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $cotacao = Cotacao::findOrFail($cotacaoId);
        abort_unless(auth()->user()->can('operar', $cotacao), 403);
        abort_unless($cotacao->requisicao_id === $this->requisicao->id, 403);

        $revogados = app(CotacaoLinkService::class)->revogar($cotacao);

        $this->recarregar();
        $this->dispatch('notify', mensagem: $revogados > 0 ? 'Link revogado.' : 'Não havia link ativo para revogar.');
    }

    /**
     * A solicitação de cotação é uma mensagem COMERCIAL (decisão 13, fundação v0.7.0):
     * quem fala com o fornecedor é a EMPRESA, não a suíte. Por isso ela sai pelo canal
     * do TENANT (domínio de envio dele, com SPF/DKIM/DMARC dele), pelo
     * `CommercialMessenger` — e não pelo mailer do app nem, muito menos, pelo remetente
     * de sistema. Sem canal ativo NÃO HÁ QUEDA para o canal da suíte: falha fechado.
     *
     * O `CommercialMessenger` enfileira internamente (`SendCommercialMessage`, que é
     * `ShouldBeEncrypted`) — o que também resolve a ressalva antiga deste mailable: o
     * token em claro da URL viaja no payload da fila CIFRADO, e a credencial é
     * reconferida no worker (canal suspenso entre o clique e a entrega não envia).
     */
    private function enviarLink(Cotacao $cotacao): void
    {
        $emitido = app(CotacaoLinkService::class)->emitir($cotacao, Carbon::parse($this->prazoResposta));
        $link = $emitido['link'];

        app(CommercialMessenger::class)->sendEmail(
            (string) $cotacao->fornecedor->contato_email,
            new SolicitacaoCotacao($cotacao, $emitido['url'], $link->referencia, $link->expires_at),
        );
    }

    /**
     * O tenant tem canal de e-mail ATIVO? Conferido ANTES de criar cotação e emitir
     * link: sem isso a tela deixaria para trás cotações "aguardando" com link emitido e
     * nenhum e-mail enviado — um estado que o comprador não tem como distinguir de
     * "fornecedor não respondeu".
     */
    private function semCanalDeEmail(): bool
    {
        if (app(CommercialMessenger::class)->hasActiveChannel(TenantChannelCredential::CHANNEL_EMAIL)) {
            return false;
        }

        $this->erroDeCanal();

        return true;
    }

    private function erroDeCanal(): void
    {
        $this->addError('cotacoes', 'Não foi possível enviar a solicitação pelo canal de e-mail desta empresa. A cotação sai pelo domínio dela, não pelo da plataforma: peça ao administrador para conferir o canal em Canais de comunicação e tente novamente.');
    }

    /** @return array<int, string> */
    private function regraPrazoResposta(): array
    {
        return ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(90)->toDateString()];
    }

    private function recarregar(): void
    {
        $this->requisicao->refresh();
        $this->requisicao->load(['cotacoes.fornecedor', 'cotacoes.criador', 'faixaAlcada']);
    }

    /** Confirma o valor oficial a partir da sugestão capturada por e-mail. */
    public function confirmarSugestao(int $cotacaoId): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::findOrFail($cotacaoId);
        abort_unless(auth()->user()->can('operar', $cotacao), 403);
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
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->requisicao->refresh();
        abort_unless($this->requisicao->status->value === 'em_cotacao', 403);

        $cotacao = Cotacao::with('fornecedor')->findOrFail($cotacaoId);
        abort_unless(auth()->user()->can('operar', $cotacao), 403);
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
        abort_unless(auth()->user()->can('compras.manage'), 403);
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

        $cotacoes = $this->requisicao->cotacoes()->whereNull('deleted_at')->with('fornecedor', 'criador', 'linkAtual')->get();
        $temVencedora = $cotacoes->where('vencedora', true)->count() === 1;
        // Cotações "aguardando" (valor null, só com sugestão) não contam para o mínimo
        // até a compradora confirmar o valor.
        $confirmadas = $cotacoes->whereNotNull('valor')->count();
        $podeConcluir = $confirmadas >= $minimoNecessario && $temVencedora;

        return view('livewire.compradora.gestao-cotacoes', compact('fornecedores', 'minimoNecessario', 'cotacoes', 'temVencedora', 'podeConcluir'))
            ->layout('components.layouts.app');
    }
}
