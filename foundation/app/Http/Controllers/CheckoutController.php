<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\ConfirmCheckoutRequest;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Support\CostaRicaTerritories;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show(Request $request, CheckoutService $checkout)
    {
        try {
            $review = $checkout->review();
        } catch (ValidationException $exception) {
            return redirect('/cart')->withErrors($exception->errors());
        }

        return Inertia::render('checkout/Index', [
            'review' => $review, 'territories' => CostaRicaTerritories::all(),
            // Do not guess how a full account name splits into given/family names.
            'prefill' => ['email' => $request->user()?->role === Role::Customer ? $request->user()->email : ''],
        ]);
    }

    public function store(ConfirmCheckoutRequest $request, CheckoutService $checkout)
    {
        $order = $checkout->confirm($request->validated(), $request->user());

        return redirect()->route('checkout.confirmation', $order->number, 303);
    }

    public function confirmation(string $number, CheckoutService $checkout)
    {
        $order = Order::where('number', $number)->firstOrFail();
        abort_unless(session()->has('checkout_owner') && hash_equals($order->owner_hash, $checkout->ownerHash()), 404);

        return Inertia::render('checkout/Confirmation', ['order' => $order->publicSummary()]);
    }
}
