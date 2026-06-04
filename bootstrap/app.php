<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\Localization;
use App\Providers\AppServiceProvider;
use App\Providers\AwsSecretsProvider;
use App\Providers\CacheFallbackProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            Localization::class
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class
        ]);
    })
    ->withProviders([
        // CacheFallbackProvider must run before anything that touches
        // cache/queue/session so Redis can be swapped for file/database
        // when Redis is unreachable.
        CacheFallbackProvider::class,
        AppServiceProvider::class,
        AwsSecretsProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
