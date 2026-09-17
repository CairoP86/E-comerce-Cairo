<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrivateCommerce
{
    public function handle(Request $request, Closure $next)
    {
        Inertia::encryptHistory();
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
