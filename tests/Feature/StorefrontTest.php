<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $name, int $bagPrice = 700, int $kiloPrice = 110, int $sortOrder = 0): Product
    {
        return Product::create([
            'name' => $name,
            'note' => null,
            'variants' => [
                ['label' => '10 kg bag', 'price' => $bagPrice],
                ['label' => 'per kg', 'price' => $kiloPrice],
            ],
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_shop_page_shows_hero_price_list_and_cart_drawer(): void
    {
        $this->makeProduct('Fresh Carrots', 700, 110);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Fresh vegetables, by the bag or by the kilo.')
            ->assertSee('Price list')
            ->assertSee('₱70/kg')
            ->assertSee('id="cart-drawer"', false);
    }

    public function test_cart_lines_carry_the_product_swatch_color(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [['variant_index' => 1, 'qty' => 2]],
        ])
            ->assertOk()
            ->assertJsonPath('lines.0.swatch', $carrots->swatchColor())
            ->assertJsonPath('summary.total', 220);
    }

    public function test_price_per_kilo_applies_only_to_multi_kilo_variants(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);

        $this->assertSame(70.0, $carrots->pricePerKilo(0));
        $this->assertNull($carrots->pricePerKilo(1));
        $this->assertNull($carrots->pricePerKilo(5));
    }

    public function test_checkout_renders_the_order_summary_and_delivery_form(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [['variant_index' => 0, 'qty' => 1]],
        ]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Your order')
            ->assertSee('name="contact_number"', false)
            ->assertSee('Place order');
    }

    /**
     * @return array<string, string>
     */
    protected function orderDetails(): array
    {
        return [
            'full_name' => 'Juan Dela Cruz',
            'contact_number' => '09171234567',
            'delivery_address' => '123 Mabini St., Quezon City',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '09:00',
        ];
    }

    protected function cartWithCarrots(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [['variant_index' => 0, 'qty' => 2]],
        ]);
    }

    public function test_placing_an_order_returns_the_thank_you_page_and_messenger_link(): void
    {
        config(['services.facebook.page_id' => '12345']);
        $this->cartWithCarrots();

        $response = $this->postJson(route('orders.store'), $this->orderDetails())->assertOk();

        $this->assertStringStartsWith('https://m.me/12345?text=', $response->json('messenger_url'));

        $this->get($response->json('confirmation_url'))
            ->assertOk()
            ->assertSee('Thank you for ordering, Juan Dela Cruz!')
            ->assertSee('Copy message')
            ->assertSee('Fresh Carrots (10 kg bag) x2', false);
    }

    public function test_placing_an_order_without_javascript_redirects_to_the_thank_you_page(): void
    {
        $this->cartWithCarrots();

        $this->post(route('orders.store'), $this->orderDetails())
            ->assertRedirectContains('/confirmation?signature=');
    }

    public function test_thank_you_page_needs_the_signed_link(): void
    {
        $this->cartWithCarrots();
        $url = $this->postJson(route('orders.store'), $this->orderDetails())->json('confirmation_url');

        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_invalid_order_details_are_rejected(): void
    {
        $this->cartWithCarrots();

        $this->postJson(route('orders.store'), ['contact_number' => '123'] + $this->orderDetails())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_number');
    }
}
