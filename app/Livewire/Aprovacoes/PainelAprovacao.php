<?php

namespace App\Livewire\Aprovacoes;

use App\Actions\AprovarEtapaAction;
use App\Actions\ReprovarRequisicaoAction;
use App\Enums\Perfil;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PainelAprovacao extends Component
{
    public int $id;

    public string $justificativa = '';

    public bool $mostrarModalAprovar = false;

    public bool $mostrarModalReprovar = false;

    /** @var array<int, bool> item_id => rejeitar? */
    public array $rejeitar = [];

    /** @var array<int, string> item_id => motivo */
    public array $motivoRejeicao = [];

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);
        $this->id = $id;
        // Valida acesso à unidade desta requisição já no mount
        $this->carregarRequisicao();
    }

    public function aprovar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);

        $this->validate(
            ['justificativa' => 'nullable|string|max:1000'],
            ['justificativa.max' => 'A justificativa não pode ultrapassar 1000 caracteres.']
        );

        $requisicao = $this->carregarRequisicao();

        $itensRejeitados = [];
        foreach ($this->rejeitar as $itemId => $marcado) {
            if ($marcado) {
                $itensRejeitados[(int) $itemId] = (string) ($this->motivoRejeicao[$itemId] ?? '');
            }
        }

        try {
            app(AprovarEtapaAction::class)->execute($requisicao, auth()->user(), $this->justificativa, $itensRejeitados);
        } catch (ValidationException $e) {
            // Mantém o modal aberto para o aprovador corrigir (ex.: motivo do item).
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('acao', $mensagem);

            return;
        }

        $this->redirect(route('aprovacoes.fila'));
    }

    public function reprovar(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);

        $this->validate(
            ['justificativa' => 'required|string|min:10|max:1000'],
            [
                'justificativa.required' => 'Informe a justificativa da reprovação.',
                'justificativa.min' => 'A justificativa deve ter ao menos 10 caracteres.',
            ]
        );

        $requisicao = $this->carregarRequisicao();

        try {
            app(ReprovarRequisicaoAction::class)->execute($requisicao, auth()->user(), $this->justificativa);
        } catch (ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->addError('acao', $mensagem);
            $this->mostrarModalReprovar = false;

            return;
        }

        $this->redirect(route('aprovacoes.fila'));
    }

    private function carregarRequisicao(): Requisicao
    {
        $requisicao = Requisicao::withoutGlobalScopes()
            ->with([
                'solicitante',
                'unidade',
                'faixaAlcada.etapas',
                'aprovacoes.aprovador',
                'cotacoes.fornecedor',
                'itens',
            ])
            ->findOrFail($this->id);

        // Verifica que o usuário é Aprovador na unidade desta requisição (anti-IDOR)
        $temAcesso = (bool) DB::table('unidade_user')
            ->where('user_id', auth()->id())
            ->where('unidade_id', $requisicao->unidade_id)
            ->where('perfil', Perfil::Aprovador->value)
            ->exists();

        abort_unless($temAcesso, 403);

        return $requisicao;
    }

    public function podeAprovar(Requisicao $requisicao): bool
    {
        $etapa = $requisicao->etapaAprovacaoAtual();
        if (! $etapa) {
            return false;
        }

        return (bool) DB::table('unidade_user')
            ->where('user_id', auth()->id())
            ->where('unidade_id', $requisicao->unidade_id)
            ->where('perfil', Perfil::Aprovador->value)
            ->where('nivel_alcada', $etapa->nivel_exigido->value)
            ->exists();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);

        $requisicao = $this->carregarRequisicao();
        $etapaAtual = $requisicao->etapaAprovacaoAtual();
        $podeAprovar = $this->podeAprovar($requisicao);

        $historico = Aprovacao::where('requisicao_id', $requisicao->id)
            ->with('aprovador')
            ->orderBy('ciclo')
            ->orderBy('ordem')
            ->get();

        return view('livewire.aprovacoes.painel-aprovacao', compact('requisicao', 'etapaAtual', 'podeAprovar', 'historico'))
            ->layout('components.layouts.app');
    }
}
