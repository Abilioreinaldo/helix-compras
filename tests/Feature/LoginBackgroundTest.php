<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tela de login renderiza com o fundo SVG executivo', function () {
    $resposta = $this->get('/login');

    $resposta->assertOk()
        ->assertSee('preserveAspectRatio="xMidYMid slice"', false) // SVG responsivo
        ->assertSee('url(#lb-bg)', false)        // gradiente de fundo escuro
        ->assertSee('url(#lb-chart)', false);    // gráfico de tendência (verde→azul)
});
