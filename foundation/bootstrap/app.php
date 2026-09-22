<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\DatabaseFailure;
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
        // Trusted proxies live in config/trustedproxy.php: this closure runs before configuration
        // is loaded, and the framework reads that key per request (see Http\Middleware\TrustProxies).
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception) {
            if (request()->is('checkout', 'checkout/*', 'admin/orders', 'admin/orders/*', 'admin/commercial/*')) {
                Log::error('Order database operation failed.', DatabaseFailure::of($exception)->logContext());

                return false;
            }
        });
        // Honest about retrying: 503 only when trying again can work, 500 when it cannot.
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('checkout', 'checkout/*', 'admin/orders', 'admin/orders/*', 'admin/commercial/*')) {
                return null;
            }
            $failure = DatabaseFailure::of($exception);
            if ($request->is('checkout', 'checkout/*')) {
                return $failure->transient
                    ? response('Tuvimos un problema momentáneo. Tu carrito se conserva: probá de nuevo en un minuto.', 503)
                    : response('No pudimos completar la operación por un problema de nuestra parte. Reintentar no lo va a resolver: ya quedó registrado. Tu carrito se conserva.', 500);
            }

            // The operator is the owner: the driver code is what makes the log entry findable.
            return $failure->transient
                ? response('Tuvimos un problema momentáneo con la base de datos. Probá de nuevo en un minuto.', 503)
                : response('La operación falló por un problema del sistema y reintentar no lo va a resolver. Quedó registrado en el log con el código '.($failure->driverCode ?? $failure->sqlstate ?? 'desconocido').'.', 500);
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
