<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_payment_methods_page_is_public_and_not_indexed(): void
    {
        $this->get('/metodos-de-pago')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('info/PaymentMethods')->where('seo.robots', 'noindex, nofollow')
        );
    }

    public function test_the_corporate_page_publishes_the_configured_channels(): void
    {
        config(['storefront.contact' => ['whatsapp' => '8342-1619', 'email' => 'corporativo@example.test']]);
        $this->get('/venta-corporativa')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('info/Corporate')
                ->where('contact.whatsapp', '8342-1619')
                // The country code is added for the link even though it is not configured.
                ->where('contact.whatsapp_url', 'https://wa.me/50683421619')
                ->where('contact.email', 'corporativo@example.test')
        );
    }

    /** The page picks its branch on these values, so a configured channel is never empty. */
    public function test_a_configured_channel_never_arrives_empty_at_the_page(): void
    {
        config(['storefront.contact' => ['whatsapp' => '8342-1619', 'email' => 'corporativo@example.test']]);
        $contact = $this->get('/venta-corporativa')->viewData('page')['props']['contact'];
        $this->assertSame(['whatsapp', 'whatsapp_url', 'email'], array_keys($contact));
        foreach ($contact as $key => $value) {
            $this->assertNotSame('', $value, $key.' llegó vacío y la página mostraría el mensaje de canal en habilitación');
        }
    }

    public function test_the_whatsapp_number_is_accepted_in_any_local_format(): void
    {
        foreach (['8342-1619', '8342 1619', '+506 8342-1619', '50683421619'] as $configured) {
            config(['storefront.contact.whatsapp' => $configured]);
            $contact = $this->get('/venta-corporativa')->viewData('page')['props']['contact'];
            $this->assertSame('https://wa.me/50683421619', $contact['whatsapp_url'], $configured);
            $this->assertSame('8342-1619', $contact['whatsapp'], $configured);
        }
    }

    public function test_the_corporate_page_invents_no_contact_when_none_is_configured(): void
    {
        config(['storefront.contact' => ['whatsapp' => '', 'email' => '']]);
        $this->get('/venta-corporativa')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('contact.whatsapp', '')->where('contact.whatsapp_url', '')->where('contact.email', '')
        );
    }

    public function test_contact_details_are_not_shared_with_every_page(): void
    {
        config(['storefront.contact' => ['whatsapp' => '8342-1619', 'email' => 'corporativo@example.test']]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->missing('identity.contact'));
    }

    public function test_the_navigation_shares_only_published_top_level_categories(): void
    {
        $root = Category::factory()->create(['name' => 'Aaa raíz']);
        Category::factory()->create(['name' => 'Bbb hija', 'parent_id' => $root->id]);
        $draft = Category::factory()->create(['name' => 'Ccc borrador', 'status' => 'draft']);
        $second = Category::factory()->create(['name' => 'Zzz raíz']);

        $this->get('/metodos-de-pago')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('navCategories', [
                ['name' => $root->name, 'slug' => $root->slug],
                ['name' => $second->name, 'slug' => $second->slug],
            ])
        );
        $this->assertNotContains($draft->slug, array_column($this->inertiaCategories(), 'slug'));
    }

    public function test_the_navigation_categories_travel_with_every_storefront_page(): void
    {
        Category::factory()->create(['name' => 'Portátiles']);
        foreach (['/', '/catalog', '/cart', '/venta-corporativa'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('navCategories', 1), $url);
        }
    }

    private function inertiaCategories(): array
    {
        return $this->get('/metodos-de-pago')->viewData('page')['props']['navCategories'];
    }
}
