<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/account');
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception) {
            if (request()->is('checkout', 'checkout/*', 'admin/orders', 'admin/orders/*', 'admin/commercial/*')) {
                Log::error('Order database operation failed.', ['sqlstate' => $exception->errorInfo[0] ?? null]);

                return false;
            }
        });
        $exceptions->render(function (QueryException $exception, Request $request) {
            if ($request->is('checkout', 'checkout/*', 'admin/orders', 'admin/orders/*', 'admin/commercial/*')) {
                return response('No pudimos completar la operación. Conservamos tu carrito; vuelve a intentarlo.', 503);
            }
        });
        $exceptions->dontFlash(['first_name', 'last_name', 'email', 'phone', 'exact_address', 'additional', 'token']);
        $exceptions->respond(function (Response $response) {
            if (request()->is('admin/commercial/*') && request()->hasSession()) {
                request()->session()->forget('_old_input');
            }
            $status = $response->getStatusCode();
            if (in_array($status, [403, 404, 419, 429], true) && ! request()->expectsJson()) {
                $response = Inertia::render('Error', ['status' => $status])->toResponse(request())->setStatusCode($status);
            }
            if (request()->is('checkout', 'checkout/*', 'admin/orders', 'admin/orders/*', 'admin/commercial/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return $response;
        });
    })->create();
