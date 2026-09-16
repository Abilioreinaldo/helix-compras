<?php

namespace App\Livewire\Relatorios;

use App\Actions\DescontoRateioAction;
use App\Enums\Perfil;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\Scopes\UnidadeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RelatorioRateioMensalCentral extends Component
{
    public string $filtroMes = '';

    public string $filtroAno = '';

    #[Locked]
    public ?int $expandidoId = null;

    #[Locked]
    public ?int $revertendoItemId = null;

    public string $motivoReversao = '';

    public function mount(): void
    {
        abort_unless($this->podeAcessar(), 403);
    }

    public function toggleExpandir(int $id): void
    {
        $this->authorizeAcesso();
        // Só estado de UI, mas o id vem do cliente: confirma a posse do rateio.
        $rateio = RateioCentral::findOrFail($id);
        abort_unless(auth()->user()->can('operar', $rateio), 403);
        $this->expandidoId = $this->expandidoId === $id ? null : $id;
    }

    public function abrirReversao(int $itemId): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);
        // Resolve e autoriza o registro AQUI (e não só no confirmar): o modal não
        // abre para um rateio de outra empresa.
        $item = RateioUnidade::findOrFail($itemId);
        abort_unless(auth()->user()->can('operar', $item), 403);
        $this->revertendoItemId = $item->id;
        $this->motivoReversao = '';
        $this->resetValidation();
    }

    public function cancelarReversao(): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);
        $this->revertendoItemId = null;
        $this->motivoReversao = '';
        $this->resetValidation();
    }

    public function confirmarReversao(): void
    {
        abort_unless(auth()->user()->can('admin.gerenciar'), 403);

        $item = RateioUnidade::with('rateioCentral')->findOrFail($this->revertendoItemId);
        abort_unless(auth()->user()->can('operar', $item), 403);

        try {
            app(DescontoRateioAction::class)->execute(
                $item->rateioCentral,
                $item,
                $this->motivoReversao,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('motivoReversao', collect($e->errors())->flatten()->first() ?? 'Falha ao reverter.');

            return;
        }

        $this->cancelarReversao();
        $this->dispatch('notify', mensagem: 'Rateio revertido com sucesso.');
    }

    /** Acesso ao relatório (mount e cada action): 403 se não {@see podeAcessar()}. */
    private function authorizeAcesso(): void
    {
        abort_unless($this->podeAcessar(), 403);
    }

    /** Admin (todas) ou Aprovador de alguma unidade (própria). */
    private function podeAcessar(): bool
    {
        $user = auth()->user();

        return $user->temPerfil(Perfil::Admin) || $this->unidadesDoGestor()->isNotEmpty();
    }

    /** IDs das unidades onde o usuário é Aprovador (gestor da unidade). */
    private function unidadesDoGestor(): Collection
    {
        // withoutGlobalScopes: perfil é vínculo global do usuário, não filtrado pela unidade da sessão.
        return auth()->user()->unidades()
            ->withoutGlobalScope(UnidadeScope::class)
            ->wherePivot('perfil', Perfil::Aprovador->value)
            ->pluck('unidades.id');
    }

    public function render(): View
    {
        abort_unless($this->podeAcessar(), 403);

        $ehAdmin = auth()->user()->temPerfil(Perfil::Admin);

        $query = RateioCentral::query()
            ->orderByDesc('ano')
            ->orderByDesc('mes');

        if ($this->filtroMes !== '') {
            $query->where('mes', (int) $this->filtroMes);
        }

        if ($this->filtroAno !== '') {
            $query->where('ano', (int) $this->filtroAno);
        }

        if ($ehAdmin) {
            $query->with(['unidades.unidade', 'unidades.movimentacoes']);
        } else {
            // Gestor vê só as linhas das suas unidades; rateios sem linha visível somem.
            $unidadeIds = $this->unidadesDoGestor();
            $query->whereHas('unidades', fn ($q) => $q->whereIn('unidade_id', $unidadeIds))
                ->with(['unidades' => fn ($q) => $q->whereIn('unidade_id', $unidadeIds)->with(['unidade', 'movimentacoes'])]);
        }

        return view('livewire.relatorios.relatorio-rateio-mensal-central', [
            'rateios' => $query->get(),
            'ehAdmin' => $ehAdmin,
        ])->layout('components.layouts.app');
    }
}
