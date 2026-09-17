<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_is_served_through_inertia_without_supplier_data(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->missing('suppliers')
            ->missing('cost')
        );
    }

    public function test_catalog_is_not_implemented_in_phase_0b(): void
    {
        $this->get('/products')->assertNotFound();
        $this->get('/checkout')->assertNotFound();
    }
}
