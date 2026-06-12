<?php

namespace App\Livewire\Aprovacoes;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FilaAprovacoes extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->temPerfil(Perfil::Aprovador), 403);

        $usuario = auth()->user();

        // Pares (unidade_id, nivel_alcada) em que o usuário é Aprovador
        $pares = $usuario->unidades()
            ->withoutGlobalScopes()
            ->wherePivot('perfil', Perfil::Aprovador->value)
            ->whereNotNull('unidade_user.nivel_alcada')
            ->get()
            ->map(fn ($u) => [
                'unidade_id' => $u->id,
                'nivel' => $u->pivot->nivel_alcada instanceof NivelAlcada
                    ? $u->pivot->nivel_alcada->value
                    : $u->pivot->nivel_alcada,
            ]);

        $requisicoes = Requisicao::withoutGlobalScopes()
            ->with(['solicitante', 'unidade'])
            ->where('status', StatusRequisicao::AguardandoAprovacao->value)
            ->where(function ($q) use ($pares) {
                foreach ($pares as $par) {
                    $q->orWhere(function ($sub) use ($par) {
                        $sub->where('unidade_id', $par['unidade_id'])
                            ->whereHas('aprovacoes', fn ($a) => $a
                                ->where('status', StatusAprovacao::Pendente->value)
                                ->where('nivel_exigido', $par['nivel'])
                                ->whereColumn('ciclo', 'requisicoes.ciclo_aprovacao')
                            );
                    });
                }
            })
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.aprovacoes.fila-aprovacoes', compact('requisicoes'))
            ->layout('components.layouts.app');
    }
}
