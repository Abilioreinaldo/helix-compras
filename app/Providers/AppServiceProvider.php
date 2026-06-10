<?php

namespace App\Providers;

use App\Models\Obra;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra serviços da aplicação.
     */
    public function register(): void {}

    /**
     * Inicializa a aplicação.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'unidade' => Unidade::class,
            'obra' => Obra::class,
            // demais models serão adicionados nas fases seguintes
        ]);
    }
}
