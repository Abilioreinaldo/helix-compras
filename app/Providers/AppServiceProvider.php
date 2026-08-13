<?php

namespace App\Providers;

use App\Imap\LeitorCaixaCotacoes;
use App\Imap\LeitorCaixaCotacoesIndisponivel;
use App\Imap\WebklexLeitorCaixaCotacoes;
use App\Models\CentroCusto;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Pagamento;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Policies\AdminPolicy;
use App\Policies\AprovacaoPolicy;
use App\Policies\EstoquePolicy;
use App\Policies\PagamentoPolicy;
use App\Policies\RelatorioPolicy;
use App\Policies\RequisicaoPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra serviços da aplicação.
     */
    public function register(): void
    {
        // Captura IMAP de cotações: usa o leitor webklex quando o IMAP está configurado;
        // senão, um fallback que não conecta em nada (não quebra o scheduler).
        $this->app->bind(LeitorCaixaCotacoes::class, function () {
            return config('mail.imap.host')
                ? new WebklexLeitorCaixaCotacoes
                : new LeitorCaixaCotacoesIndisponivel;
        });
    }

    /**
     * Inicializa a aplicação.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'unidade' => Unidade::class,
            'obra' => Obra::class,
            'fornecedor' => Fornecedor::class,
            'centro_custo' => CentroCusto::class,
        ]);

        // ADR-015: consome o pedido de compra da loja (Store) e grava no inbox
        // pedidos_loja_recebidos. O evento chega pelo receptor inbound assinado da
        // foundation (/api/inbound/events) e roda aqui no ProcessDomainEvent.
        app(\Helix\Foundation\Services\Platform\Event\SubscriberRegistry::class)->subscribe(
            \App\Subscribers\IngerirPedidoLoja::EVENTO,
            \App\Subscribers\IngerirPedidoLoja::class,
        );

        Gate::policy(Pagamento::class, PagamentoPolicy::class);
        Gate::policy(Requisicao::class, RequisicaoPolicy::class);

        // Aprovações: Gates nomeados (Requisicao já tem policy própria).
        Gate::define('aprovacao.acessar-fila', [AprovacaoPolicy::class, 'acessarFila']);
        Gate::define('aprovacao.acessar', [AprovacaoPolicy::class, 'acessar']);
        Gate::define('aprovacao.decidir', [AprovacaoPolicy::class, 'decidir']);

        // Estoque/Almoxarife: acesso ao módulo (estado/saldo/lote seguem nas Actions).
        Gate::define('estoque.gerenciar', [EstoquePolicy::class, 'gerenciar']);

        // Relatórios consolidados (RateioMensalCentral tem regra própria no componente).
        Gate::define('relatorio.ver', [RelatorioPolicy::class, 'ver']);

        // Administração (cadastros/parâmetros) — mesma checagem do middleware `admin`.
        Gate::define('admin.gerenciar', [AdminPolicy::class, 'gerenciar']);
    }
}
