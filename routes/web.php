<?php

use App\Http\Controllers\BaixarPdfPedidoCompraController;
use App\Http\Controllers\DownloadArquivoCotacaoController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ForcaTrocaSenha;
use App\Livewire\Admin\Alcadas\ListaAlcadas;
use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Livewire\Admin\CatalogoItens\ReconciliacaoSaldos;
use App\Livewire\Admin\CentrosCusto\ListaCentrosCusto;
use App\Livewire\Admin\Fornecedores\ListaFornecedores;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Livewire\Almoxarife\AtendimentoRequisicoesMaterial;
use App\Livewire\Almoxarife\GestaoPedidosRecebimento;
use App\Livewire\Almoxarife\Inventario;
use App\Livewire\Almoxarife\RegistroRecebimento;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Livewire\Aprovacoes\FilaAprovacoes;
use App\Livewire\Aprovacoes\PainelAprovacao;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TrocarSenha;
use App\Livewire\Compradora\DetalhePedidoCompra;
use App\Livewire\Compradora\FormularioPedidoCompra;
use App\Livewire\Compradora\GestaoCotacoes;
use App\Livewire\Compradora\GestaoPedidosCompra;
use App\Livewire\Compradora\ItensARepor;
use App\Livewire\Compradora\TriagemRequisicoes;
use App\Livewire\Relatorios\ComprasEmergenciais;
use App\Livewire\Relatorios\CustoObra;
use App\Livewire\Relatorios\GastosCentroCusto;
use App\Livewire\Relatorios\GastosFornecedor;
use App\Livewire\Relatorios\RequisicoesAprovador;
use App\Livewire\Requisicoes\DetalheRequisicao;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Livewire\Requisicoes\ListaRequisicoes;
use App\Livewire\Solicitante\RequisicoesMaterial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware(['auth', ForcaTrocaSenha::class])->group(function () {
    Route::get('/senha/trocar', TrocarSenha::class)->name('senha.trocar');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Fase 2 — Requisições (qualquer usuário autenticado)
    Route::get('/requisicoes', ListaRequisicoes::class)->name('requisicoes.index');
    Route::get('/requisicoes/nova', FormularioRequisicao::class)->name('requisicoes.criar');
    Route::get('/requisicoes/{id}', DetalheRequisicao::class)->name('requisicoes.detalhe');
    Route::get('/requisicoes/{id}/editar', FormularioRequisicao::class)->name('requisicoes.editar');

    // Fase 2 — Triagem (Compradora)
    Route::get('/compradora/triagem', TriagemRequisicoes::class)->name('compradora.triagem');

    // v1.1-D — Itens a repor (Compradora + Admin)
    Route::get('/compradora/itens-a-repor', ItensARepor::class)->name('compradora.itens-a-repor');

    // Fase 5 — Pedidos de Compra (rota estática antes das dinâmicas)
    Route::get('/compradora/pedidos/{id}/pdf', BaixarPdfPedidoCompraController::class)->name('compradora.pedidos.pdf');
    Route::get('/compradora/pedidos', GestaoPedidosCompra::class)->name('compradora.pedidos.index');
    Route::get('/compradora/pedidos/{id}/editar', FormularioPedidoCompra::class)->name('compradora.pedidos.editar');
    Route::get('/compradora/pedidos/{id}', DetalhePedidoCompra::class)->name('compradora.pedidos.detalhe');

    // Fase 3 — Cotações (Compradora) — rota estática 'arquivo' deve vir ANTES do parâmetro dinâmico {id}
    Route::get('/compradora/cotacoes/arquivo/{cotacao}', DownloadArquivoCotacaoController::class)->name('compradora.cotacoes.arquivo');
    Route::get('/compradora/cotacoes/{id}', GestaoCotacoes::class)->name('compradora.cotacoes');

    // Fase 4 — Aprovações
    Route::get('/aprovacoes', FilaAprovacoes::class)->name('aprovacoes.fila');
    Route::get('/aprovacoes/{id}', PainelAprovacao::class)->name('aprovacoes.painel');

    // Fase 6 — Recebimento (Almoxarife)
    Route::get('/almoxarife/recebimentos', GestaoPedidosRecebimento::class)->name('almoxarife.recebimentos.index');
    Route::get('/almoxarife/recebimentos/{id}', RegistroRecebimento::class)->name('almoxarife.recebimentos.registrar');

    // Fase 7 — Estoque (Almoxarife)
    Route::get('/almoxarife/estoque', SaldosEstoque::class)->name('almoxarife.estoque.index');

    // v1.1-C — RIM (Requisição Interna de Material)
    Route::get('/solicitante/requisicoes-material', RequisicoesMaterial::class)->name('solicitante.rim.index');
    Route::get('/almoxarife/atendimento-material', AtendimentoRequisicoesMaterial::class)->name('almoxarife.rim.index');

    // v1.1-C — Inventário (Almoxarife / Admin)
    Route::get('/almoxarife/inventario', Inventario::class)->name('almoxarife.inventario.index');

    // Fase 8 — Relatórios (Admin e CompradoraSenior)
    Route::get('/relatorios/gastos-cc', GastosCentroCusto::class)->name('relatorios.gastos-cc');
    Route::get('/relatorios/pendentes-aprovador', RequisicoesAprovador::class)->name('relatorios.pendentes-aprovador');
    Route::get('/relatorios/custo-obra', CustoObra::class)->name('relatorios.custo-obra');
    Route::get('/relatorios/emergenciais', ComprasEmergenciais::class)->name('relatorios.emergenciais');
    Route::get('/relatorios/gastos-fornecedor', GastosFornecedor::class)->name('relatorios.gastos-fornecedor');

    // Fase 1 — somente Admin
    Route::middleware(AdminMiddleware::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/unidades', ListaUnidades::class)->name('unidades');
        Route::get('/usuarios', ListaUsuarios::class)->name('usuarios');
        Route::get('/fornecedores', ListaFornecedores::class)->name('fornecedores');
        Route::get('/alcadas', ListaAlcadas::class)->name('alcadas');
        Route::get('/centros-custo', ListaCentrosCusto::class)->name('centros-custo');
        Route::get('/catalogo-itens', ListaCatalogoItens::class)->name('catalogo-itens');
        Route::get('/reconciliacao-saldos', ReconciliacaoSaldos::class)->name('reconciliacao-saldos');
    });
});

Route::redirect('/', '/login');
