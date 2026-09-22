<?php

use App\Exceptions\MarketplaceException;
use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureShopIsActive;
use App\Http\Middleware\EnsureShopIsManageable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'shop.active' => EnsureShopIsActive::class,
            'shop.manageable' => EnsureShopIsManageable::class,
            'api.admin' => EnsureApiAdmin::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin*')) {
                return route('admin.login');
            }

            return $request->is('shop*', 'api/shop*')
                ? route('shop.login')
                : route('client.login');
        });

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin*')
            ? route('admin.dashboard')
            : route('client.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Domain failures carry a stable machine code, e.g. SHOP_SUSPENDED, SHOP_NAME_TAKEN.
        $exceptions->render(function (MarketplaceException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(
                    array_merge(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->context),
                    $e->status,
                );
            }

            // Refusals must stay real 403s so they are not mistaken for a flash message.
            if ($e->status === 403) {
                abort(403, $e->getMessage());
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
