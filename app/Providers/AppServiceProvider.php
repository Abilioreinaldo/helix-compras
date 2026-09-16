<?php

namespace App\Providers;

use App\Imap\LeitorCaixaCotacoes;
use App\Imap\LeitorCaixaCotacoesIndisponivel;
use App\Imap\WebklexLeitorCaixaCotacoes;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\EstoqueMinimo;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\PedidoLojaRecebido;
use App\Models\PrecoHomologado;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\ReconciliacaoBancaria;
use App\Models\Requisicao;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\AprovacaoPolicy;
use App\Policies\EstoquePolicy;
use App\Policies\PagamentoPolicy;
use App\Policies\RecursoDoTenantPolicy;
use App\Policies\RequisicaoMaterialPolicy;
use App\Policies\RequisicaoPolicy;
use App\Policies\UsuarioPolicy;
use App\Subscribers\IngerirPedidoLoja;
use Helix\Foundation\Services\Platform\Event\SubscriberRegistry;
use Illuminate\Database\Eloquent\Model;
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
        // v0.2.x: tenant_id saiu do $fillable das models da fundação (e do User
        // deste app). Sem isto, um create(['tenant_id' => ...]) seria aceito e a
        // coluna descartada EM SILÊNCIO — o registro nasceria no tenant do
        // contexto (ou nulo), que é exatamente o caminho para o dado errado.
        Model::preventSilentlyDiscardingAttributes();

        Relation::morphMap([
            'unidade' => Unidade::class,
            'obra' => Obra::class,
            'fornecedor' => Fornecedor::class,
            'centro_custo' => CentroCusto::class,
        ]);

        // ADR-015: consome o pedido de compra da loja (Store) e grava no inbox
        // pedidos_loja_recebidos. O evento chega pelo receptor inbound assinado da
        // foundation (/api/inbound/events) e roda aqui no ProcessDomainEvent.
        app(SubscriberRegistry::class)->subscribe(
            IngerirPedidoLoja::EVENTO,
            IngerirPedidoLoja::class,
        );

        Gate::policy(Pagamento::class, PagamentoPolicy::class);
        Gate::policy(Requisicao::class, RequisicaoPolicy::class);
        Gate::policy(RequisicaoMaterial::class, RequisicaoMaterialPolicy::class);

        // Policy de POSSE (ability `operar`) dos demais registros que as telas
        // manipulam. Desde a fundação v0.2.0 o Gate::before NÃO curto-circuita mais
        // quando há um model no argumento: a permissão do catálogo responde "pode
        // essa AÇÃO?" e a policy responde "sobre ESSE registro?" — ver
        // RecursoDoTenantPolicy.
        foreach ([
            CatalogoItem::class,
            CentroCusto::class,
            Cotacao::class,
            EstoqueMinimo::class,
            FaixaAlcada::class,
            Fornecedor::class,
            Obra::class,
            PedidoCompra::class,
            PedidoLojaRecebido::class,
            PrecoHomologado::class,
            RateioCentral::class,
            RateioUnidade::class,
            ReconciliacaoBancaria::class,
            SaldoEstoque::class,
            Unidade::class,
        ] as $recurso) {
            Gate::policy($recurso, RecursoDoTenantPolicy::class);
        }

        // Usuário tem regra própria: o recorte é a MEMBERSHIP, não users.tenant_id.
        Gate::policy(User::class, UsuarioPolicy::class);

        // Gates nomeados de ACESSO. O Gate::before da fundação tenta primeiro
        // hasPermission(ability): estes nomes (*.gerenciar/*.ver/*.acessar*) são
        // deliberadamente distintos dos slugs do catálogo (*.manage/*.view), então
        // sempre caem nas policies abaixo — que, por sua vez, decidem por permissão
        // (temPerfil) ou por vínculo de unidade.
        // Aprovações: Gates nomeados (Requisicao já tem policy própria).
        Gate::define('aprovacao.acessar-fila', [AprovacaoPolicy::class, 'acessarFila']);
        Gate::define('aprovacao.acessar', [AprovacaoPolicy::class, 'acessar']);
        Gate::define('aprovacao.decidir', [AprovacaoPolicy::class, 'decidir']);

        // Estoque/Almoxarife: acesso ao módulo (estado/saldo/lote seguem nas Actions).
        Gate::define('estoque.gerenciar', [EstoquePolicy::class, 'gerenciar']);

        // Relatórios consolidados: sem gate próprio — os componentes checam direto a
        // permissão do catálogo `compras.manage` (a antiga `relatorio.ver` espelhava
        // exatamente podeVerTodasUnidades() = compras.manage). O RateioMensalCentral
        // tem regra própria no componente.

        // Administração (cadastros/parâmetros) — mesma checagem do middleware `admin`.
        Gate::define('admin.gerenciar', [AdminPolicy::class, 'gerenciar']);
    }
}
