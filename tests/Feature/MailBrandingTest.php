<?php

use App\Mail\RequisicaoAprovada;
use App\Models\Requisicao;

it('e-mail de requisição aprovada sai com a marca HELIX e sem Laravel', function () {
    $requisicao = Requisicao::factory()->create(['codigo' => 'REQ-2026-000123']);

    $html = (new RequisicaoAprovada($requisicao))->render();

    expect($html)
        ->toContain('HELIX')
        ->not->toContain('notification-logo')   // logo padrão do Laravel
        ->not->toContain('>Laravel<');           // app.name não vaza no corpo
});
