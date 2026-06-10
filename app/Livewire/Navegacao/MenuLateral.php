<?php

namespace App\Livewire\Navegacao;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MenuLateral extends Component
{
    public function render()
    {
        $user = Auth::user();

        $itens = [['label' => 'Dashboard', 'href' => route('dashboard'), 'todos' => true]];

        if ($user->is_admin) {
            $itens = array_merge($itens, [
                ['label' => 'Unidades', 'href' => '#'],
                ['label' => 'Usuários', 'href' => '#'],
                ['label' => 'Fornecedores', 'href' => '#'],
                ['label' => 'Alçadas', 'href' => '#'],
                ['label' => 'Centros de Custo', 'href' => '#'],
            ]);
        }

        if ($user->is_compradora) {
            $itens = array_merge($itens, [
                ['label' => 'Fila de Requisições', 'href' => '#'],
                ['label' => 'Cotações', 'href' => '#'],
                ['label' => 'Pedidos de Compra', 'href' => '#'],
            ]);
        }

        if (! $user->is_admin && ! $user->is_compradora) {
            $perfis = $user->unidades->pluck('pivot.perfil')->unique();

            if ($perfis->contains('aprovador')) {
                $itens[] = ['label' => 'Aprovações Pendentes', 'href' => '#'];
            }

            if ($perfis->contains('solicitante')) {
                $itens = array_merge($itens, [
                    ['label' => 'Minhas Requisições', 'href' => '#'],
                    ['label' => 'Nova Requisição', 'href' => '#'],
                ]);
            }

            if ($perfis->contains('almoxarife')) {
                $itens = array_merge($itens, [
                    ['label' => 'Recebimentos', 'href' => '#'],
                    ['label' => 'Estoque', 'href' => '#'],
                ]);
            }
        }

        return view('livewire.navegacao.menu-lateral', ['itens' => $itens]);
    }
}
