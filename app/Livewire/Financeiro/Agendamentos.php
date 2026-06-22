<?php

namespace App\Livewire\Financeiro;

use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Agendamentos extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerPagamentos(), 403);
    }

    /**
     * @return Collection<int, Pagamento>
     */
    private function proximos()
    {
        $limite = Carbon::today()->addDays(30)->toDateString();

        return Pagamento::with('fornecedor')
            ->whereIn('status', [StatusPagamento::Agendado->value, StatusPagamento::Pendente->value, StatusPagamento::Parcial->value])
            ->whereDate('data_vencimento', '<=', $limite)
            ->orderBy('data_vencimento')
            ->get();
    }

    /** Exporta os pagamentos dos próximos 30 dias em CSV (lista para o banco). */
    public function exportar(): StreamedResponse
    {
        abort_unless(auth()->user()->podeVerPagamentos(), 403);

        $pagamentos = $this->proximos();

        return response()->streamDownload(function () use ($pagamentos) {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, ['referencia', 'fornecedor', 'vencimento', 'valor'], ';');
            foreach ($pagamentos as $p) {
                fputcsv($saida, [
                    // Sanitiza contra CSV/formula injection na exportação.
                    $this->sanitizar($p->referencia_banco ?? ('PAG-'.$p->id)),
                    $this->sanitizar($p->fornecedor?->nome_fantasia ?? '—'),
                    $p->data_vencimento?->format('d/m/Y'),
                    number_format((float) ($p->valor_total - $p->valor_pago), 2, ',', ''),
                ], ';');
            }
            fclose($saida);
        }, 'agendamentos-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Previne injeção de fórmula em planilhas (=, +, -, @ no início). */
    private function sanitizar(string $valor): string
    {
        return preg_match('/^[=+\-@]/', $valor) ? "'".$valor : $valor;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerPagamentos(), 403);

        return view('livewire.financeiro.agendamentos', [
            'pagamentos' => $this->proximos(),
        ])->layout('components.layouts.app');
    }
}
