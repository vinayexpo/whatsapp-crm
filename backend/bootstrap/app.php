<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Registered via then()+Route::group() instead of the api: shorthand: the shorthand
        // auto-prefixes routes with "api/", but this app's front controller already lives in
        // an /api/ subfolder, so the real request path never contains that prefix and every
        // route would 404.
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('api')->group(__DIR__.'/../routes/api.php');
        },
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/widget/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
