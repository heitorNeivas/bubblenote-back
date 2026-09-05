<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Força "Accept: application/json" em toda requisição da API.
 *
 * Sem isso, quando o auth:sanctum falha numa chamada que não mandou o header
 * (ex.: fetch server-to-server do BFF), o Laravel tenta redirecionar para
 * route('login') — que não existe numa API — e responde 500 em vez de 401.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}