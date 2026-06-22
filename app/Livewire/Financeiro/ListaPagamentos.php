<?php

namespace App\Livewire\Financeiro;

use App\Actions\AgendarPagamentoAction;
use App\Actions\CancelarPagamentoAction;
use App\Actions\RegistrarPagamentoAction;
use App\Enums\MetodoPagamento;
use App\Enums\StatusPagamento;
use App\Models\Banco;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ListaPagamentos extends Component
{
    use WithPagination;

    public string $filtroStatus = '';

    public string $filtroBancoId = '';

    public string $filtroFornecedorId = '';

    public string $filtroVencimentoAte = '';

    // Modal de registro de pagamento
    public bool $mostrarRegistrar = false;

    public ?int $pagamentoId = null;

    public string $valorPago = '';

    public string $dataPagamento = '';

    public string $metodo = '';

    public string $bancoId = '';

    public string $numeroCheque = '';

    public string $referenciaBanco = '';

    public string $observacoes = '';

    // Modais de agendamento e cancelamento
    public bool $mostrarAgendar = false;

    public string $dataAgendamento = '';

    public bool $mostrarCancelar = false;

    public string $motivoCancelamento = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->podeVerPagamentos(), 403);
    }

    public function updatingFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroBancoId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroFornecedorId(): void
    {
        $this->resetPage();
    }

    private function pagamentoSelecionado(): Pagamento
    {
        $pag = Pagamento::findOrFail($this->pagamentoId);

        return $pag;
    }

    // ─── Registrar ───────────────────────────────────────────────────────────

    public function abrirRegistrar(int $id): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
        $pag = Pagamento::findOrFail($id);

        $this->pagamentoId = $id;
        $this->valorPago = (string) $pag->valor_total;
        $this->dataPagamento = now()->toDateString();
        $this->metodo = '';
        $this->bancoId = '';
        $this->numeroCheque = '';
        $this->referenciaBanco = '';
        $this->observacoes = '';
        $this->resetValidation();
        $this->mostrarRegistrar = true;
    }

    public function registrar(): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);

        $this->validate([
            'valorPago' => 'required|numeric|min:0.01',
            'dataPagamento' => 'required|date',
            'metodo' => 'required|in:'.implode(',', array_column(MetodoPagamento::cases(), 'value')),
            'bancoId' => 'nullable|exists:bancos,id',
            'numeroCheque' => 'nullable|string|max:50',
            'referenciaBanco' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            app(RegistrarPagamentoAction::class)->execute(
                $this->pagamentoSelecionado(),
                (float) $this->valorPago,
                $this->dataPagamento,
                MetodoPagamento::from($this->metodo),
                $this->bancoId !== '' ? Banco::find($this->bancoId) : null,
                $this->referenciaBanco ?: null,
                $this->numeroCheque ?: null,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('formulario', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->mostrarRegistrar = false;
        $this->dispatch('notify', mensagem: 'Pagamento registrado.');
    }

    // ─── Agendar ─────────────────────────────────────────────────────────────

    public function abrirAgendar(int $id): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
        $this->pagamentoId = $id;
        $this->dataAgendamento = now()->addDay()->toDateString();
        $this->resetValidation();
        $this->mostrarAgendar = true;
    }

    public function agendar(): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
        $this->validate(['dataAgendamento' => 'required|date']);

        try {
            app(AgendarPagamentoAction::class)->execute($this->pagamentoSelecionado(), $this->dataAgendamento, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('formulario', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->mostrarAgendar = false;
        $this->dispatch('notify', mensagem: 'Pagamento agendado.');
    }

    // ─── Cancelar ────────────────────────────────────────────────────────────

    public function abrirCancelar(int $id): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
        $this->pagamentoId = $id;
        $this->motivoCancelamento = '';
        $this->resetValidation();
        $this->mostrarCancelar = true;
    }

    public function cancelar(): void
    {
        abort_unless(auth()->user()->podeGerenciarPagamentos(), 403);
        $this->validate(['motivoCancelamento' => 'required|string|min:3|max:1000']);

        try {
            app(CancelarPagamentoAction::class)->execute($this->pagamentoSelecionado(), $this->motivoCancelamento, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('formulario', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->mostrarCancelar = false;
        $this->dispatch('notify', mensagem: 'Pagamento cancelado.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->podeVerPagamentos(), 403);

        $base = Pagamento::query()->with(['fornecedor', 'banco']);

        $pagamentos = $base->clone()
            ->when($this->filtroStatus !== '', fn ($q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroBancoId !== '', fn ($q) => $q->where('banco_id', (int) $this->filtroBancoId))
            ->when($this->filtroFornecedorId !== '', fn ($q) => $q->where('fornecedor_id', (int) $this->filtroFornecedorId))
            ->when($this->filtroVencimentoAte !== '', fn ($q) => $q->whereDate('data_vencimento', '<=', $this->filtroVencimentoAte))
            ->orderBy('data_vencimento')
            ->paginate(20);

        $emAberto = [StatusPagamento::Pendente->value, StatusPagamento::Agendado->value, StatusPagamento::Parcial->value, StatusPagamento::Vencido->value];

        return view('livewire.financeiro.lista-pagamentos', [
            'pagamentos' => $pagamentos,
            'bancos' => Banco::ativo()->orderBy('nome')->get(),
            'fornecedores' => Fornecedor::orderBy('nome_fantasia')->get(['id', 'nome_fantasia']),
            'totalAPagar' => (float) Pagamento::whereIn('status', $emAberto)->sum(DB::raw('valor_total - valor_pago')),
            'totalPagoMes' => (float) Pagamento::where('status', StatusPagamento::Pago->value)
                ->whereYear('data_pagamento', now()->year)->whereMonth('data_pagamento', now()->month)->sum('valor_pago'),
            'totalVencido' => (float) Pagamento::whereIn('status', [StatusPagamento::Pendente->value, StatusPagamento::Parcial->value, StatusPagamento::Agendado->value])
                ->whereDate('data_vencimento', '<', now()->toDateString())->sum(DB::raw('valor_total - valor_pago')),
            'totalAgendado' => (float) Pagamento::where('status', StatusPagamento::Agendado->value)->sum(DB::raw('valor_total - valor_pago')),
        ])->layout('components.layouts.app');
    }
}
