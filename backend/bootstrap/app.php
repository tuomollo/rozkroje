<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
//        $middleware->append(HandleCors::class);
        $middleware->append(\App\Http\Middleware\OwnCors::class);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        $middleware->api(prepend: [
            SubstituteBindings::class,
        ]);
        $middleware->web(prepend: [
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always render JSON for API routes to avoid login redirects when unauthenticated.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
