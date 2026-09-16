<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxies confiáveis (ALB/nginx): os IPs vêm de config('trustedproxy.proxies')
        // (env TRUSTED_PROXIES, CSV ou "*", lida em config/trustedproxy.php — sobrevive
        // ao config:cache; um env() aqui rodaria antes do .env ser carregado). Sem
        // TRUSTED_PROXIES nenhum proxy é confiado e os X-Forwarded-* são ignorados.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Degradação isolada (auditoria multitenant 2026-09-15): teto de requisições
        // por tenant+usuário em todo o grupo web (inclui /livewire/update e as rotas
        // da fundação). O login continua com o limiter próprio da fundação (por e-mail).
        $middleware->web(append: ['throttle:web']);
    })
    ->booted(function (): void {
        // Chave = tenant ativo + usuário: um tenant (ou um usuário em loop) esgota só a
        // própria cota. Visitante (tela de login) cai no IP.
        RateLimiter::for('web', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(300)->by(
                $user ? 'tenant:'.$user->getActiveTenantId().'|user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip()
            );
        });

        // Link público de resposta de cotação (decisão 11): teto por IP (varredura de
        // tokens) E por token (martelar um link vazado). O token entra só como hash.
        RateLimiter::for('cotacao-link', fn (Request $request) => [
            Limit::perMinute(30)->by('cotacao-link:ip:'.$request->ip()),
            Limit::perMinute(10)->by('cotacao-link:token:'.hash('sha256', (string) $request->route('token'))),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
