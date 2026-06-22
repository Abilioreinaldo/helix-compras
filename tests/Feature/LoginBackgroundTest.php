<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tela de login renderiza com o fundo SVG executivo', function () {
    $resposta = $this->get('/login');

    $resposta->assertOk()
        ->assertSee('preserveAspectRatio="xMidYMid slice"', false) // SVG responsivo
        ->assertSee('url(#lb-bg)', false)          // gradiente de fundo (azul → roxo)
        ->assertSee('url(#lb-glow-cyan)', false)   // glow neon cyan
        ->assertSee('Gestão Inteligente', false)   // painel de branding (split layout)
        ->assertSee('Fluxo de compra', false);     // diagrama de fluxo
});
