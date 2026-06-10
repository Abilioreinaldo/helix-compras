<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcaTrocaSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user() &&
            $request->user()->precisa_trocar_senha &&
            ! $request->routeIs('senha.trocar') &&
            ! $request->routeIs('logout')
        ) {
            return redirect()->route('senha.trocar');
        }

        return $next($request);
    }
}
