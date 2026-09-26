<?php

namespace Tests\Feature;

use App\Models\Order;
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

    public function test_shop_page_shows_only_the_vegetables_and_cart_drawer(): void
    {
        $this->makeProduct('Fresh Carrots', 700, 110);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Fresh Carrots')
            ->assertSee('10 kg bag')
            ->assertSee('Add to cart')
            ->assertSee('class="qty-box"', false)
            ->assertSee('id="cart-drawer"', false)
            ->assertDontSee('Price list')
            ->assertDontSee('How ordering works');
    }

    public function test_end_of_list_shows_cart_total_and_order_button(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [['variant_index' => 0, 'qty' => 2]],
        ]);

        $this->get(route('products.index'))
            ->assertSeeInOrder(['Fresh Carrots', 'Cart total', '₱1,400', 'ORDER'])
            ->assertSee('href="'.route('checkout.index').'"', false);
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
            ->assertSee('Summary')
            ->assertSee('Details')
            ->assertSee('name="contact_number"', false)
            ->assertSee('CP or Viber number')
            ->assertSee('Orders received before')
            ->assertDontSee('name="preferred_date"', false)
            ->assertDontSee('name="order_notes"', false)
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

    public function test_per_kilo_sizes_allow_half_kilos_with_exact_centavos(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 115);

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [
                ['variant_index' => 0, 'qty' => 1.5], // bags are whole only → rounds to 2
                ['variant_index' => 1, 'qty' => 0.5], // per kg allows halves
            ],
        ])
            ->assertOk()
            ->assertJsonPath('lines.0.qty', 2)
            ->assertJsonPath('lines.1.qty', 0.5)
            ->assertJsonPath('lines.1.min', 0.5)
            ->assertJsonPath('lines.1.line_total', 57.5)
            ->assertJsonPath('summary.total', 1457.5)
            ->assertJsonPath('summary.item_count', 2);

        $this->get(route('products.index'))->assertSee('min 0.5 kg');
    }

    public function test_per_kilo_sizes_go_in_tenths_of_a_kilo_from_half_a_kilo(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');
        $setKilos = fn ($qty) => $this->postJson(route('cart.update'), [
            'product_id' => $carrots->id,
            'variant_index' => 1,
            'qty' => $qty,
        ])->json('lines.0.qty');

        $this->assertEquals(0.7, $setKilos(0.7));
        $this->assertEquals(1.3, $setKilos(1.3));
        $this->assertEquals(0.8, $setKilos(0.76));
        $this->assertEquals(0.5, $setKilos(0.2)); // below the minimum → half a kilo
    }

    public function test_half_kilo_orders_keep_centavos_in_the_order_and_messenger_text(): void
    {
        config(['services.facebook.page_id' => '12345']);
        $carrots = $this->makeProduct('Fresh Carrots', 700, 115);

        $this->postJson(route('cart.add'), [
            'product_id' => $carrots->id,
            'items' => [['variant_index' => 1, 'qty' => 1.5]],
        ]);

        $url = $this->postJson(route('orders.store'), $this->orderDetails())->json('confirmation_url');
        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(1.5, $order->items->first()->qty);
        $this->assertSame(172.5, $order->total);
        $this->assertStringContainsString('Fresh Carrots (per kg) x1.5 = ₱172.50', $order->messengerText());
        $this->assertStringContainsString('Total: ₱172.50', $order->messengerText());
        $this->assertStringContainsString('CP or Viber: 09171234567', $order->messengerText());
        $this->assertStringContainsString('Delivery Address: 123 Mabini St., Quezon City', $order->messengerText());
        $this->assertStringNotContainsString('Preferred:', $order->messengerText());

        $this->get($url)->assertSee('1.5 × ₱115')->assertSee('₱172.50');
    }

    public function test_invalid_order_details_are_rejected(): void
    {
        $this->cartWithCarrots();

        $this->postJson(route('orders.store'), ['contact_number' => '123'] + $this->orderDetails())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_number');
    }
}
