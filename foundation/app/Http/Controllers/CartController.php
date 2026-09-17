<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    public function show(CartService $cart)
    {
        $seo = [
            'title' => 'Tu carrito · '.config('storefront.name'),
            'description' => 'Revisa los productos y cantidades de tu carrito. No necesitas crear una cuenta.',
            'url' => route('cart.show'), 'image' => null, 'robots' => 'noindex, nofollow',
            'site_name' => config('storefront.name'), 'locale' => config('storefront.locale'),
        ];

        return Inertia::render('cart/Index', ['cart' => $cart->snapshot(), 'seo' => $seo])->withViewData(['seo' => $seo])
            ->toResponse(request())->header('Cache-Control', 'private, no-store');
    }

    public function add(Request $request, CartService $cart)
    {
        return $this->change($request, $cart, 'add', 'Producto agregado al carrito.');
    }

    public function update(Request $request, CartService $cart, string $line)
    {
        return $this->change($request, $cart, 'update', 'Cantidad actualizada.', $line);
    }

    public function remove(Request $request, CartService $cart, string $line)
    {
        return $this->change($request, $cart, 'remove', 'Producto retirado del carrito.', $line);
    }

    public function clear(Request $request, CartService $cart)
    {
        return $this->change($request, $cart, 'clear', 'Carrito vaciado.');
    }

    private function change(Request $request, CartService $cart, string $action, string $message, ?string $line = null)
    {
        $rules = ['mutation_id' => ['required', 'uuid'], 'revision' => ['required', 'integer', 'min:0', 'max:2147483647']];
        if (in_array($action, ['add', 'update'], true)) {
            $rules['quantity'] = ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY];
        }
        if ($action === 'add') {
            $rules['product_slug'] = ['required', 'string', 'max:180'];
        }
        if (array_diff(array_keys($request->all()), [...array_keys($rules), '_token', '_method'])) {
            throw ValidationException::withMessages(['cart' => 'La solicitud contiene datos que no se pueden modificar. Actualiza la página e inténtalo de nuevo.']);
        }
        $data = $request->validate($rules, [
            'quantity.required' => 'Indica una cantidad.', 'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima es 1. Para quitar el producto, usa Eliminar.',
            'quantity.max' => 'El límite temporal es de 99 unidades por producto; no indica inventario.',
        ]);
        $changed = $cart->mutate($action, $data, $line);

        return back()->with('cart_status', $changed ? $message : 'La actualización ya se había aplicado.');
    }
}
