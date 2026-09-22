<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use App\Support\OperatorTurn;
use App\Support\StorefrontNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            // Contact details are not part of the shared identity: only the corporate page asks for them.
            'identity' => Arr::except(config('storefront'), ['contact']),
            'auth' => ['user' => $request->user()?->only(['id', 'name', 'email', 'role', 'email_verified_at'])],
            'status' => fn () => $request->session()->get('status'),
            'cartSummary' => fn () => app(CartService::class)->summary(),
            // The header category trigger needs these on every storefront page, not only on the home page.
            'navCategories' => fn () => StorefrontNavigation::categories(),
            'cartStatus' => fn () => $request->session()->get('cart_status'),
            // Sidebar counters: private pages only, so staff browsing the store never pay for the freshness query.
            'operatorTurn' => fn () => $request->is('admin', 'admin/*', 'account') && $request->user()?->can('access-operations')
                ? app(OperatorTurn::class)->counts() : null,
        ];
    }
}
