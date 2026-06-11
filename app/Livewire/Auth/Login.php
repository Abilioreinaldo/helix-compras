<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|min:6')]
    public string $senha = '';

    public bool $lembrar = false;

    public function autenticar(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->senha], $this->lembrar)) {
            $this->addError('formulario', 'E-mail ou senha incorretos.');

            return;
        }

        session()->regenerate();

        if (Auth::user()->precisa_trocar_senha) {
            $this->redirect(route('senha.trocar'), navigate: true);

            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
