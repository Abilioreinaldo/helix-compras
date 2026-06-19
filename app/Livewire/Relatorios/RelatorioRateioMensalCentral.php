<?php

namespace App\Livewire\Relatorios;

use App\Actions\DescontoRateioAction;
use App\Enums\Perfil;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RelatorioRateioMensalCentral extends Component
{
    public string $filtroMes = '';

    public string $filtroAno = '';

    public ?int $expandidoId = null;

    public ?int $revertendoItemId = null;

    public string $motivoReversao = '';

    public function mount(): void
    {
        abort_unless($this->podeAcessar(), 403);
    }

    public function toggleExpandir(int $id): void
    {
        $this->expandidoId = $this->expandidoId === $id ? null : $id;
    }

    public function abrirReversao(int $itemId): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);
        $this->revertendoItemId = $itemId;
        $this->motivoReversao = '';
        $this->resetValidation();
    }

    public function cancelarReversao(): void
    {
        $this->revertendoItemId = null;
        $this->motivoReversao = '';
        $this->resetValidation();
    }

    public function confirmarReversao(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Admin), 403);

        $item = RateioUnidade::with('rateioCentral')->findOrFail($this->revertendoItemId);

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

    /** Admin (todas) ou Aprovador de alguma unidade (própria). */
    private function podeAcessar(): bool
    {
        $user = auth()->user();

        return $user->temPerfil(Perfil::Admin) || $this->unidadesDoGestor()->isNotEmpty();
    }

    /** IDs das unidades onde o usuário é Aprovador (gestor da unidade). */
    private function unidadesDoGestor(): Collection
    {
        return auth()->user()->unidades()
            ->withoutGlobalScopes()
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
