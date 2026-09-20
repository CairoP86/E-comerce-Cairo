<?php

namespace App\Http\Controllers;

use App\Support\StorefrontContact;
use Inertia\Inertia;

/**
 * Static storefront information pages. No database access and no integration: the content
 * describes the manual process that is actually in place today (ADR-009).
 */
class InfoPageController extends Controller
{
    public function payments()
    {
        return $this->page(
            'info/PaymentMethods',
            [],
            'Métodos de pago',
            'Cómo pagar tu pedido por SINPE Móvil o transferencia bancaria, y qué ocurre después de confirmarlo.',
            route('pages.payments'),
        );
    }

    public function corporate()
    {
        return $this->page(
            'info/Corporate',
            ['contact' => StorefrontContact::public()],
            'Venta corporativa',
            'Cotizaciones para empresas, instituciones y compras por volumen, atendidas fuera del carrito.',
            route('pages.corporate'),
        );
    }

    private function page(string $component, array $props, string $title, string $description, string $url)
    {
        $seo = [
            'title' => $title.' · '.config('storefront.name'),
            'description' => $description,
            'url' => $url, 'image' => null, 'robots' => 'noindex, nofollow',
            'site_name' => config('storefront.name'), 'locale' => config('storefront.locale'),
        ];

        return Inertia::render($component, [...$props, 'seo' => $seo])->withViewData(['seo' => $seo]);
    }
}
