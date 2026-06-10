<?php

test('a raiz redireciona para o login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
