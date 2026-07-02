<?php

namespace App\Livewire;

use Helix\Foundation\Services\Platform\Identity\TwoFactorService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Segurança da conta — 2FA (TOTP) no app de Compras. Habilitar (QR + confirmação),
 * ver/gerar códigos de recuperação e desabilitar (vedado a quem é obrigado pela
 * política). Reusa o TwoFactorService da foundation.
 */
#[Layout('components.layouts.app')]
class Account2FA extends Component
{
    public string $code = '';

    /** @var array<int, string> */
    public array $novosCodigos = [];

    public function habilitar(TwoFactorService $tfa): void
    {
        $tfa->enable(auth()->user());
        $this->reset(['code', 'novosCodigos']);
    }

    public function confirmar(TwoFactorService $tfa): void
    {
        $this->validate(['code' => ['required', 'string']]);

        if (! $tfa->confirm(auth()->user(), $this->code)) {
            $this->addError('code', 'Código inválido. Verifique o relógio do dispositivo e tente de novo.');

            return;
        }

        $this->novosCodigos = $tfa->recoveryCodes(auth()->user()->refresh());
        $this->reset('code');
        session()->flash('ok', '2FA ativado. Guarde os códigos de recuperação abaixo.');
    }

    public function regenerar(TwoFactorService $tfa): void
    {
        $this->novosCodigos = $tfa->regenerateRecoveryCodes(auth()->user());
        session()->flash('ok', 'Novos códigos de recuperação gerados — os anteriores foram invalidados.');
    }

    public function desabilitar(TwoFactorService $tfa): void
    {
        if ($tfa->requiresTwoFactor(auth()->user())) {
            session()->flash('erro', '2FA é obrigatório para o seu perfil e não pode ser desativado.');

            return;
        }

        $tfa->disable(auth()->user());
        $this->reset(['code', 'novosCodigos']);
        session()->flash('ok', '2FA desativado.');
    }

    public function render(TwoFactorService $tfa): View
    {
        $user = auth()->user();
        $pendente = $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled();

        return view('livewire.account2fa', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pendente' => $pendente,
            'obrigatorio' => $tfa->requiresTwoFactor($user),
            'qrSvg' => $pendente ? $tfa->qrCodeSvg($user) : null,
            'qrUri' => $pendente ? $tfa->qrCodeUri($user) : null,
            'secret' => $pendente ? $user->two_factor_secret : null,
        ]);
    }
}
