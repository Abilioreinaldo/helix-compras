<?php

namespace App\Livewire\Relatorios;

use App\Enums\StatusRequisicao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TempoAprovacao extends Component
{
    public int $ano;

    public int $mes = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);
        $this->ano = (int) now()->year;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerTodasUnidades(), 403);

        // Duração do ciclo de aprovação em horas (fração). Driver-aware como no v1.1-B:
        // - MySQL/MariaDB: TIMESTAMPDIFF(SECOND, ...) / 3600 (julianday não existe no MySQL).
        // - SQLite: (julianday(fim) - julianday(início)) * 24.
        // Apenas ciclos COMPLETOS entram (status Aprovada + ambos os timestamps); o whereNotNull
        // exclui requisição não aprovada / ciclo aberto e evita subtração com nulo.
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $duracaoHoras = 'TIMESTAMPDIFF(SECOND, r.aprovacao_iniciada_em, r.aprovada_em) / 3600';
        } else {
            $duracaoHoras = '(julianday(r.aprovada_em) - julianday(r.aprovacao_iniciada_em)) * 24';
        }

        $resultados = DB::table('requisicoes as r')
            ->leftJoin('faixas_alcada as fa', 'fa.id', '=', 'r.faixa_alcada_id')
            ->where('r.status', StatusRequisicao::Aprovada->value)
            ->whereNull('r.deleted_at')
            ->whereNotNull('r.aprovacao_iniciada_em')
            ->whereNotNull('r.aprovada_em')
            ->whereYear('r.aprovada_em', $this->ano)
            ->when($this->mes > 0, fn ($q) => $q->whereMonth('r.aprovada_em', $this->mes))
            ->select(
                DB::raw('COALESCE(fa.nome, \'Sem faixa\') as faixa_nome'),
                DB::raw('COUNT(r.id) as total_requisicoes'),
                DB::raw("AVG({$duracaoHoras}) as horas_media"),
                DB::raw("MIN({$duracaoHoras}) as horas_min"),
                DB::raw("MAX({$duracaoHoras}) as horas_max"),
            )
            ->groupBy('faixa_nome', 'fa.valor_minimo')
            ->orderBy('fa.valor_minimo')
            ->get();

        return view('livewire.relatorios.tempo-aprovacao', [
            'resultados' => $resultados,
            'totalRequisicoes' => $resultados->sum('total_requisicoes'),
            'anos' => range((int) now()->year, (int) now()->year - 4),
            'meses' => [
                0 => 'Ano inteiro',
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ],
        ])->layout('components.layouts.app');
    }
}
