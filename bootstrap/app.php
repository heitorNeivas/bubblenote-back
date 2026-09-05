<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Toda a API responde JSON: garante que o auth:sanctum falho vire
        // 401 JSON (e nao um redirect para route('login'), que nao existe).
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Autenticacao SPA do Sanctum (sessao + cookie).
        // Prepende EnsureFrontendRequestsAreStateful ao grupo "api": para
        // requisicoes vindas dos dominios em SANCTUM_STATEFUL_DOMAINS, aplica
        // EncryptCookies, VerifyCsrfToken, StartSession e AddQueuedCookiesToResponse,
        // dando acesso a $request->session() e ao guard "web".
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda requisicao em /api/* responde JSON, mesmo sem header Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Envelope de erro consistente para as excecoes mais comuns da API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            [$status, $message] = match (true) {
                $e instanceof ValidationException => [422, 'Os dados enviados são inválidos.'],
                $e instanceof AuthenticationException => [401, 'Não autenticado.'],
                $e instanceof AuthorizationException => [403, 'Esta ação não é autorizada.'],
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => [404, 'Recurso não encontrado.'],
                $e instanceof ThrottleRequestsException => [429, 'Muitas requisições. Tente novamente em instantes.'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Erro na requisição.'],
                default => [500, 'Erro interno no servidor.'],
            };

            $payload = ['message' => $message];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();
