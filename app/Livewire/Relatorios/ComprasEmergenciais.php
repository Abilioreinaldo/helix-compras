<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusRequisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ComprasEmergenciais extends Component
{
    public int $ano;

    public int $mes;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);
        $this->ano = (int) now()->year;
        $this->mes = (int) now()->month;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('compras.manage'), 403);

        $tenantId = auth()->user()->getActiveTenantId();

        // Valor por requisição usando cascata: PC emitido > cotação vencedora > estimativa.
        // Query builder não passa pelo BelongsToTenant: TODO recorte de tenant é explícito.
        //
        // 2ª auditoria adversarial: as três derivadas eram `DB::raw` SEM filtro de tenant.
        // O resultado final saía certo (o join pende de `r.id`, já recortado), mas o banco
        // agregava a INSTALAÇÃO INTEIRA antes de juntar: o relatório de um cliente pequeno
        // pagava o volume do maior — degradação cross-tenant, e um plano que piora à
        // medida que a base cresce para todos. O filtro agora entra DENTRO de cada
        // derivada (leftJoinSub, com binding — não interpolação).
        $pcVal = DB::table('itens_pedido_compra as ipc')
            ->where('ipc.tenant_id', $tenantId)
            ->join('pedidos_compra as pc', fn ($join) => $join
                ->on('pc.id', '=', 'ipc.pedido_compra_id')
                ->where('pc.tenant_id', $tenantId)
                ->where('pc.status', 'emitido')
                ->whereNull('pc.deleted_at'))
            ->whereNull('ipc.deleted_at')
            ->groupBy('ipc.requisicao_id')
            ->select('ipc.requisicao_id', DB::raw('SUM(ipc.valor_total) AS total'));

        $cotVal = DB::table('cotacoes')
            ->where('tenant_id', $tenantId)
            ->where('vencedora', true)
            ->whereNull('deleted_at')
            ->groupBy('requisicao_id')
            ->select('requisicao_id', DB::raw('MAX(valor) AS valor'));

        $estVal = DB::table('requisicao_itens')
            ->where('tenant_id', $tenantId)
            ->groupBy('requisicao_id')
            ->select('requisicao_id', DB::raw('SUM(quantidade * valor_unitario_estimado) AS total'));

        $resultados = DB::table('requisicoes as r')
            ->where('r.tenant_id', $tenantId)
            ->join('users as u', 'u.id', '=', 'r.solicitante_id')
            // `un.tenant_id` faltava: o join casava por `unidades.id` e, com uma unidade
            // de outro tenant referenciada, trazia o NOME dela para a tela deste.
            ->join('unidades as un', fn ($join) => $join
                ->on('un.id', '=', 'r.unidade_id')
                ->where('un.tenant_id', $tenantId))
            ->leftJoinSub($pcVal, 'pc_val', 'pc_val.requisicao_id', '=', 'r.id')
            ->leftJoinSub($cotVal, 'cot_val', 'cot_val.requisicao_id', '=', 'r.id')
            ->leftJoinSub($estVal, 'est_val', 'est_val.requisicao_id', '=', 'r.id')
            ->where('r.is_emergencial', true)
            ->where('r.status', '!=', StatusRequisicao::Cancelada->value)
            ->whereNull('r.deleted_at')
            ->whereYear('r.submetida_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('r.submetida_em', $this->mes))
            ->select(
                'r.unidade_id',
                'un.nome as unidade_nome',
                'r.solicitante_id',
                'u.name as solicitante_nome',
                DB::raw('COUNT(r.id) as total_emergenciais'),
                DB::raw('SUM(COALESCE(pc_val.total, cot_val.valor, est_val.total, 0)) as total_valor'),
            )
            ->groupBy('r.unidade_id', 'un.nome', 'r.solicitante_id', 'u.name')
            ->orderBy('un.nome')
            ->orderByDesc('total_emergenciais')
            ->get();

        return view('livewire.relatorios.compras-emergenciais', [
            'resultados' => $resultados,
            'totalEmergenciais' => $resultados->sum('total_emergenciais'),
            'totalValor' => $resultados->sum('total_valor'),
            'anos' => range((int) now()->year, (int) now()->year - 4),
            'meses' => [
                0 => 'Todos os meses',
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ],
        ])->layout('components.layouts.app');
    }
}
