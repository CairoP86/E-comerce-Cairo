<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $exceptions->respond(function (Response $response) {
            $status = $response->getStatusCode();
            if (in_array($status, [403, 404, 419, 429], true) && ! request()->expectsJson()) {
                return Inertia::render('Error', ['status' => $status])->toResponse(request())->setStatusCode($status);
            }

            return $response;
        });
    })->create();
