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
}
