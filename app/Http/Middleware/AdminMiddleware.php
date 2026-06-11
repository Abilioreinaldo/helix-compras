<?php

namespace App\Http\Middleware;

use App\Enums\Perfil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->user()->temPerfil(Perfil::Admin)) {
            abort(403);
        }

        return $next($request);
    }
}
