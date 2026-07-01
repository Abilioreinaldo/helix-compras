<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tela de login renderiza com o fundo SVG executivo', function () {
    $resposta = $this->get('/login');

    // Login compartilhado da fundação (helix/foundation): fundo SVG executivo.
    $resposta->assertOk()
        ->assertSee('preserveAspectRatio="xMidYMid slice"', false) // SVG responsivo
        ->assertSee('url(#lb-bg)', false)          // gradiente de fundo (azul → roxo)
        ->assertSee('url(#lb-glow-cyan)', false);  // glow neon cyan
});
