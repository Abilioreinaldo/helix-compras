<?php

namespace App\Livewire\Relatorios;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RequisicoesAprovador extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        // Query builder não passa pelo BelongsToTenant: o recorte de tenant é explícito.
        $tenantId = auth()->user()->getActiveTenantId();

        // Somente aprovações pendentes do ciclo atual de cada requisição.
        $resultados = DB::table('aprovacoes as a')
            ->join('requisicoes as r', function ($join) {
                $join->on('r.id', '=', 'a.requisicao_id')
                    ->whereColumn('a.ciclo', 'r.ciclo_aprovacao');
            })
            ->join('users as u', 'u.id', '=', 'a.aprovador_id')
            // `users` é a identidade compartilhada da suíte e não tem recorte de tenant
            // próprio (users.tenant_id é só o tenant HOME). O recorte vem da MEMBERSHIP
            // ativa no tenant corrente — o mesmo critério da tela de usuários.
            ->join('tenant_user as tu', function ($join) use ($tenantId) {
                $join->on('tu.user_id', '=', 'u.id')
                    ->where('tu.tenant_id', '=', $tenantId)
                    ->where('tu.status', '=', 'active');
            })
            ->where('a.tenant_id', $tenantId)
            ->where('r.tenant_id', $tenantId)
            ->where('a.status', 'pendente')
            ->whereNull('a.deleted_at')
            ->whereNull('r.deleted_at')
            ->select(
                'u.id as aprovador_id',
                'u.name as aprovador_nome',
                DB::raw('COUNT(DISTINCT r.id) as total_pendentes'),
                DB::raw('MIN(r.submetida_em) as mais_antiga'),
            )
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_pendentes')
            ->get();

        return view('livewire.relatorios.requisicoes-aprovador', [
            'resultados' => $resultados,
        ])->layout('components.layouts.app');
    }
}
