<?php

namespace App\Livewire\Almoxarife;

use App\Actions\AbrirSessaoInventarioAction;
use App\Actions\AplicarInventarioAction;
use App\Actions\CancelarSessaoInventarioAction;
use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Models\SessaoInventario;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Inventario extends Component
{
    // ─── Form de abertura ────────────────────────────────────────────────────
    public string $depositoAbertura = '';

    public bool $mostrarFormAbrir = false;

    // ─── Sessão ativa ────────────────────────────────────────────────────────
    public ?int $sessaoAtivaId = null;

    // ─── Contagem ────────────────────────────────────────────────────────────
    /** @var array<int, string> */
    public array $quantidadesContadas = [];

    // ─── Aplicar ─────────────────────────────────────────────────────────────
    public bool $mostrarModalAplicar = false;

    public string $justificativaAplicar = '';

    // ─── Feedback ────────────────────────────────────────────────────────────
    public string $erro = '';

    public function mount(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
    }

    private function usuarioAutorizado(): bool
    {
        $usuario = auth()->user();

        return $usuario->temPerfil(Perfil::Admin) || $usuario->temPerfil(Perfil::Almoxarife);
    }

    public function abrirFormAbrir(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
        $this->mostrarFormAbrir = true;
        $this->depositoAbertura = '';
        $this->erro = '';
    }

    public function fecharFormAbrir(): void
    {
        $this->mostrarFormAbrir = false;
        $this->depositoAbertura = '';
    }

    public function abrirSessao(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
        $this->erro = '';

        $usuario = auth()->user();

        // Para Almoxarife: usa a primeira unidade com esse perfil
        // Para Admin: usa a primeira unidade (simplificação; em produção seria dropdown)
        $unidade = $usuario->temPerfil(Perfil::Admin)
            ? Unidade::first()
            : $usuario->unidades()
                ->withoutGlobalScopes()
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->first();

        if (! $unidade) {
            $this->erro = 'Nenhuma unidade disponível para inventário.';

            return;
        }

        $deposito = $this->depositoAbertura !== '' ? $this->depositoAbertura : null;

        try {
            $sessao = app(AbrirSessaoInventarioAction::class)->execute($unidade, $deposito, $usuario);
            $this->sessaoAtivaId = $sessao->id;
            $this->quantidadesContadas = [];
            $this->mostrarFormAbrir = false;
            $this->dispatch('notify', mensagem: 'Sessão de inventário aberta.');
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first() ?? 'Erro ao abrir sessão.';
        }
    }

    public function abrirModalAplicar(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
        $this->mostrarModalAplicar = true;
        $this->justificativaAplicar = '';
        $this->erro = '';
    }

    public function fecharModalAplicar(): void
    {
        $this->mostrarModalAplicar = false;
        $this->justificativaAplicar = '';
    }

    public function aplicar(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
        $this->erro = '';

        $sessao = SessaoInventario::find($this->sessaoAtivaId);

        if (! $sessao) {
            $this->erro = 'Sessão não encontrada.';

            return;
        }

        // Salvar quantidades contadas nos itens (validação server-side: não-negativa)
        foreach ($this->quantidadesContadas as $itemId => $qtd) {
            if ($qtd === '' || ! is_numeric($qtd)) {
                continue;
            }

            if ((float) $qtd < 0) {
                $this->erro = 'Quantidade contada não pode ser negativa.';

                return;
            }

            $sessao->itens()->where('id', $itemId)->update(['quantidade_contada' => (float) $qtd]);
        }

        $sessao->load('itens.saldoEstoque');

        try {
            app(AplicarInventarioAction::class)->execute($sessao, $this->justificativaAplicar, auth()->user());
            $this->sessaoAtivaId = null;
            $this->quantidadesContadas = [];
            $this->mostrarModalAplicar = false;
            $this->dispatch('notify', mensagem: 'Inventário aplicado com sucesso.');
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first() ?? 'Erro ao aplicar inventário.';
            $this->mostrarModalAplicar = false;
        }
    }

    public function cancelar(): void
    {
        abort_unless($this->usuarioAutorizado(), 403);
        $this->erro = '';

        $sessao = SessaoInventario::find($this->sessaoAtivaId);

        if (! $sessao) {
            $this->erro = 'Sessão não encontrada.';

            return;
        }

        try {
            app(CancelarSessaoInventarioAction::class)->execute($sessao, auth()->user());
            $this->sessaoAtivaId = null;
            $this->quantidadesContadas = [];
            $this->dispatch('notify', mensagem: 'Sessão de inventário cancelada.');
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first() ?? 'Erro ao cancelar sessão.';
        }
    }

    public function render(): View
    {
        abort_unless($this->usuarioAutorizado(), 403);

        $sessaoAtiva = $this->sessaoAtivaId
            ? SessaoInventario::with('itens.saldoEstoque')->find($this->sessaoAtivaId)
            : null;

        // Histórico restrito às unidades do usuário (Admin vê todas; Almoxarife só as suas).
        $usuario = auth()->user();
        $unidadeIds = $usuario->temPerfil(Perfil::Admin)
            ? null
            : $usuario->unidades()->withoutGlobalScopes()
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->pluck('unidades.id');

        $historico = SessaoInventario::with('unidade')
            ->when($unidadeIds !== null, fn ($q) => $q->whereIn('unidade_id', $unidadeIds))
            ->whereIn('status', [StatusInventario::Concluido->value, StatusInventario::Cancelado->value])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.almoxarife.inventario', compact('sessaoAtiva', 'historico'))
            ->layout('components.layouts.app');
    }
}
