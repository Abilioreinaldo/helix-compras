<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrocarSenha extends Component
{
    #[Validate('required')]
    public string $senha_atual = '';

    #[Validate('required|min:8|confirmed')]
    public string $nova_senha = '';

    public string $nova_senha_confirmation = '';

    public function salvar(): void
    {
        $this->validate();

        if (! Hash::check($this->senha_atual, Auth::user()->password)) {
            $this->addError('senha_atual', 'Senha atual incorreta.');

            return;
        }

        $user = Auth::user();
        $user->password = Hash::make($this->nova_senha);
        $user->precisa_trocar_senha = false;
        $user->save();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.trocar-senha');
    }
}
