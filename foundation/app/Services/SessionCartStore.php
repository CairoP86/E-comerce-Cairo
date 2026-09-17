<?php

namespace App\Services;

use App\Contracts\CartStore;
use Illuminate\Contracts\Session\Session;

class SessionCartStore implements CartStore
{
    public function __construct(private Session $session) {}

    public function read(): array
    {
        return $this->session->get('shopping_cart', [
            'items' => [], 'currency' => null, 'revision' => 0, 'mutations' => [],
        ]);
    }

    public function write(array $cart): void
    {
        $this->session->put('shopping_cart', $cart);
    }
}
