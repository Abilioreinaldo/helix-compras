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
                ['label' => 'Catálogo de Itens', 'href' => route('admin.catalogo-itens')],
                ['label' => 'Reconciliação de Saldos', 'href' => route('admin.reconciliacao-saldos')],
            ]);
        }

        if ($isCompradora) {
            $itens = array_merge($itens, [
                ['label' => 'Triagem', 'href' => route('compradora.triagem')],
                ['label' => 'Requisições', 'href' => route('requisicoes.index')],
                ['label' => 'Pedidos de Compra', 'href' => route('compradora.pedidos.index')],
            ]);
        }

        if ($isAdmin || $isCompradora) {
            $itens = array_merge($itens, [
                ['label' => 'Itens a Repor', 'href' => route('compradora.itens-a-repor')],
                ['label' => 'Gastos por CC', 'href' => route('relatorios.gastos-cc')],
                ['label' => 'Gastos por Fornecedor', 'href' => route('relatorios.gastos-fornecedor')],
                ['label' => 'Pendentes por Aprovador', 'href' => route('relatorios.pendentes-aprovador')],
                ['label' => 'Custo por Obra', 'href' => route('relatorios.custo-obra')],
                ['label' => 'Compras Emergenciais', 'href' => route('relatorios.emergenciais')],
            ]);
        }

        if ($user->temPerfil(Perfil::Aprovador)) {
            $itens[] = ['label' => 'Aprovações', 'href' => route('aprovacoes.fila')];
        }

        if (! $isAdmin && ! $isCompradora) {
            if ($user->temPerfil(Perfil::Solicitante)) {
                $itens = array_merge($itens, [
                    ['label' => 'Minhas Requisições', 'href' => route('requisicoes.index')],
                    ['label' => 'Nova Requisição', 'href' => route('requisicoes.criar')],
                    ['label' => 'Requisições de Material', 'href' => route('solicitante.rim.index')],
                ]);
            }

            if ($user->temPerfil(Perfil::Almoxarife)) {
                $itens = array_merge($itens, [
                    ['label' => 'Recebimentos', 'href' => route('almoxarife.recebimentos.index')],
                    ['label' => 'Estoque', 'href' => route('almoxarife.estoque.index')],
                    ['label' => 'Atendimento de Material', 'href' => route('almoxarife.rim.index')],
                    ['label' => 'Inventário', 'href' => route('almoxarife.inventario.index')],
                ]);
            }
        }

        return view('livewire.navegacao.menu-lateral', ['itens' => $itens]);
    }
}
