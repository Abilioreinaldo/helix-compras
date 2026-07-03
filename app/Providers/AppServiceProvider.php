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
use App\Policies\AprovacaoPolicy;
use App\Policies\PagamentoPolicy;
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

        Gate::policy(Pagamento::class, PagamentoPolicy::class);
        Gate::policy(Requisicao::class, RequisicaoPolicy::class);

        // Aprovações: Gates nomeados (Requisicao já tem policy própria).
        Gate::define('aprovacao.acessar-fila', [AprovacaoPolicy::class, 'acessarFila']);
        Gate::define('aprovacao.acessar', [AprovacaoPolicy::class, 'acessar']);
        Gate::define('aprovacao.decidir', [AprovacaoPolicy::class, 'decidir']);
    }
}
