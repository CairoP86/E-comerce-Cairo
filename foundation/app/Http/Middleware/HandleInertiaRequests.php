<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'identity' => config('storefront'),
            'auth' => ['user' => $request->user()?->only(['id', 'name', 'email', 'role', 'email_verified_at'])],
            'status' => fn () => $request->session()->get('status'),
            'cartSummary' => fn () => app(CartService::class)->summary(),
            'cartStatus' => fn () => $request->session()->get('cart_status'),
        ];
    }
}
