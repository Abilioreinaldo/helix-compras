<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redireciona para o setup de 2FA quando obrigatório e não configurado', function () {
    config(['twofactor.enforce' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('seguranca.2fa'));
});

it('a rota de setup de 2FA responde 200 (não quebra por rota inexistente)', function () {
    config(['twofactor.enforce' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/seguranca/2fa')->assertOk();
});

it('não redireciona quando o enforce está desligado', function () {
    config(['twofactor.enforce' => false]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk();
});
