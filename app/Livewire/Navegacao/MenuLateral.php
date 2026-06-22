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

        /** @var array<string, array<int, array{label: string, href: string, route: string, icon: string}>> $grupos */
        $grupos = [
            'Geral' => [
                $this->item('Dashboard', 'dashboard', 'dashboard'),
            ],
        ];

        if ($isCompradora) {
            $grupos['Compras'] = [
                $this->item('Triagem', 'compradora.triagem', 'inbox'),
                $this->item('Requisições', 'requisicoes.index', 'document'),
                $this->item('Pedidos de Compra', 'compradora.pedidos.index', 'cart'),
            ];
        }

        if ($isAdmin || $isCompradora) {
            $grupos['Compras'][] = $this->item('Itens a Repor', 'compradora.itens-a-repor', 'trending-down');

            $grupos['Relatórios'] = [
                $this->item('Gastos por CC', 'relatorios.gastos-cc', 'chart-bar'),
                $this->item('Gastos por Fornecedor', 'relatorios.gastos-fornecedor', 'dollar'),
                $this->item('Tempo de Aprovação', 'relatorios.tempo-aprovacao', 'clock'),
                $this->item('Posição de Estoque', 'relatorios.posicao-estoque', 'cube'),
                $this->item('Consumo por Unidade', 'relatorios.consumo-unidade', 'chart-pie'),
                $this->item('Comparativo entre Unidades', 'relatorios.comparativo-unidades', 'swap'),
                $this->item('Pendentes por Aprovador', 'relatorios.pendentes-aprovador', 'check-badge'),
                $this->item('Custo por Obra', 'relatorios.custo-obra', 'building'),
                $this->item('Compras Emergenciais', 'relatorios.emergenciais', 'bolt'),
            ];
        }

        if ($user->temPerfil(Perfil::Aprovador)) {
            $grupos['Aprovações'] = [
                $this->item('Aprovações', 'aprovacoes.fila', 'check-badge'),
            ];
        }

        if (! $isAdmin && ! $isCompradora) {
            if ($user->temPerfil(Perfil::Solicitante)) {
                $grupos['Minhas Requisições'] = [
                    $this->item('Minhas Requisições', 'requisicoes.index', 'document'),
                    $this->item('Nova Requisição', 'requisicoes.criar', 'plus'),
                    $this->item('Requisições de Material', 'solicitante.rim.index', 'hand'),
                ];
            }

            if ($user->temPerfil(Perfil::Almoxarife)) {
                $grupos['Estoque'] = [
                    $this->item('Recebimentos', 'almoxarife.recebimentos.index', 'package'),
                    $this->item('Estoque', 'almoxarife.estoque.index', 'cube'),
                    $this->item('Atendimento de Material', 'almoxarife.rim.index', 'hand'),
                    $this->item('Inventário', 'almoxarife.inventario.index', 'clipboard'),
                ];
            }
        }

        if ($isAdmin) {
            $grupos['Administração'] = [
                $this->item('Unidades', 'admin.unidades', 'building'),
                $this->item('Usuários', 'admin.usuarios', 'users'),
                $this->item('Fornecedores', 'admin.fornecedores', 'truck'),
                $this->item('Alçadas', 'admin.alcadas', 'scale'),
                $this->item('Centros de Custo', 'admin.centros-custo', 'tag'),
                $this->item('Catálogo de Itens', 'admin.catalogo-itens', 'book'),
                $this->item('Reconciliação de Saldos', 'admin.reconciliacao-saldos', 'refresh'),
            ];
        }

        return view('livewire.navegacao.menu-lateral', ['grupos' => array_filter($grupos)]);
    }

    /**
     * @return array{label: string, href: string, route: string, icon: string}
     */
    private function item(string $label, string $route, string $icon): array
    {
        return [
            'label' => $label,
            'href' => route($route),
            'route' => $route,
            'icon' => $icon,
        ];
    }
}
