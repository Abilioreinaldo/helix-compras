<?php

namespace App\Livewire\Navegacao;

use App\Enums\Perfil;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MenuLateral extends Component
{
    public function render()
    {
        $user = Auth::user();

        $isAdmin = $user->temPerfil(Perfil::Admin);
        $isCompradora = $user->temPerfil(Perfil::CompradoraSenior);

        $itens = [['label' => 'Dashboard', 'href' => route('dashboard'), 'todos' => true]];

        if ($isAdmin) {
            $itens = array_merge($itens, [
                ['label' => 'Unidades', 'href' => route('admin.unidades')],
                ['label' => 'Usuários', 'href' => route('admin.usuarios')],
                ['label' => 'Fornecedores', 'href' => route('admin.fornecedores')],
                ['label' => 'Alçadas', 'href' => route('admin.alcadas')],
                ['label' => 'Centros de Custo', 'href' => route('admin.centros-custo')],
            ]);
        }

        if ($isCompradora) {
            $itens = array_merge($itens, [
                ['label' => 'Fila de Requisições', 'href' => '#'],
                ['label' => 'Cotações', 'href' => '#'],
                ['label' => 'Pedidos de Compra', 'href' => '#'],
            ]);
        }

        if (! $isAdmin && ! $isCompradora) {
            if ($user->temPerfil(Perfil::Aprovador)) {
                $itens[] = ['label' => 'Aprovações Pendentes', 'href' => '#'];
            }

            if ($user->temPerfil(Perfil::Solicitante)) {
                $itens = array_merge($itens, [
                    ['label' => 'Minhas Requisições', 'href' => '#'],
                    ['label' => 'Nova Requisição', 'href' => '#'],
                ]);
            }

            if ($user->temPerfil(Perfil::Almoxarife)) {
                $itens = array_merge($itens, [
                    ['label' => 'Recebimentos', 'href' => '#'],
                    ['label' => 'Estoque', 'href' => '#'],
                ]);
            }
        }

        return view('livewire.navegacao.menu-lateral', ['itens' => $itens]);
    }
}
