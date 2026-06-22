<?php

namespace App\Providers;

use App\Imap\LeitorCaixaCotacoes;
use App\Imap\LeitorCaixaCotacoesIndisponivel;
use App\Imap\WebklexLeitorCaixaCotacoes;
use App\Models\CentroCusto;
use App\Models\Fornecedor;
use App\Models\Obra;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    }
}
