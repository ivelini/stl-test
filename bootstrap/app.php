<?php

use App\Exceptions\CapacityExhaustedException;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\HoldStateConflictException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (Application $app) {
            Route::middleware(['api'])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (CapacityExhaustedException $e) {
            return response()->json(['message' => 'Capacity exhausted'], 409);
        });

        $exceptions->render(function (HoldStateConflictException $e) {
            return response()->json(['message' => 'Hold state conflict'], 409);
        });

        $exceptions->render(function (HoldExpiredException $e) {
            return response()->json(['message' => 'Hold expired'], 422);
        });
    })->create();
