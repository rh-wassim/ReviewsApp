<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation échouée',
                    'errors'  => $e->errors(),
                ], 422);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'Accès refusé'], 403);
            }
            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Introuvable'], 404);
            }
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Erreur',
                ], $e->getStatusCode());
            }

            return response()->json([
                'message' => 'Erreur serveur',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        });
    })->create();
