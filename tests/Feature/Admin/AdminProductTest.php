<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

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

    protected function makeFruit(string $name): Product
    {
        return tap($this->makeProduct($name))->update(['category' => Product::CATEGORY_FRUIT]);
    }

    /**
     * The faked public disk, typed so editors know about its test assertions (assertExists, assertMissing).
     */
    protected function publicDisk(): FilesystemAdapter
    {
        return Storage::disk('public');
    }

    /**
     * A real 1×1 PNG, since fake image generation needs the GD extension, which isn't installed.
     */
    protected function photo(string $name = 'veggie.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        );
    }

    public function test_admin_sees_every_veggie_with_its_prices(): void
    {
        $this->makeProduct('Fresh Carrots', 700, 110);
        $this->makeProduct('Fresh Garlic', 2200, 250, 1);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSeeInOrder(['Fresh Carrots', 'Fresh Garlic'])
            ->assertSee('value="2200"', false);
    }

    public function test_admin_can_add_a_veggie_that_appears_first_on_the_store(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
                'category' => 'vegetable',
                'note' => '(Batangas)',
                'variants' => [
                    ['label' => '5 kg bag', 'price' => '450'],
                    ['label' => 'per kg', 'price' => '95'],
                ],
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('status');

        $okra = Product::where('name', 'Fresh Okra')->firstOrFail();

        $this->assertSame('(Batangas)', $okra->note);
        $this->assertSame([
            ['label' => '5 kg bag', 'price' => 450],
            ['label' => 'per kg', 'price' => 95],
        ], $okra->variants);
        $this->assertSame(0, $okra->sort_order);
        $this->assertSame(1, $carrots->refresh()->sort_order);

        $this->get(route('products.index'))->assertSeeInOrder(['Fresh Okra', '₱450', 'Fresh Carrots']);
        $this->get(route('admin.products.index'))->assertSeeInOrder(['Fresh Okra', 'Fresh Carrots']);
    }

    public function test_adding_a_veggie_requires_a_unique_name_and_valid_prices(): void
    {
        $this->makeProduct('Fresh Carrots');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Carrots',
                'category' => 'vegetable',
                'variants' => [
                    ['label' => 'per kg', 'price' => '0'],
                    ['label' => 'Per KG', 'price' => '12.5'],
                ],
            ])
            ->assertSessionHasErrors(['name', 'variants.0.price', 'variants.1.label', 'variants.1.price']);

        $this->assertSame(1, Product::count());
    }

    public function test_admin_can_edit_a_veggie(): void
    {
        $product = $this->makeProduct('Fresh Carrots');

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Fresh Carrots',
                'category' => 'vegetable',
                'note' => '(Benguet)',
                'variants' => [
                    ['label' => '10 kg bag', 'price' => '750'],
                ],
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();

        $this->assertSame('(Benguet)', $product->note);
        $this->assertSame([['label' => '10 kg bag', 'price' => 750]], $product->variants);
    }

    public function test_admin_can_move_a_product_to_the_fruits_page(): void
    {
        $saba = $this->makeProduct('Saging Saba');

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $saba), [
                'name' => 'Saging Saba',
                'category' => 'fruit',
                'variants' => $saba->variants,
            ])
            ->assertRedirect(route('admin.products.index', ['category' => 'fruit']));

        $this->assertSame(Product::CATEGORY_FRUIT, $saba->refresh()->category);

        $this->get(route('products.fruits'))->assertSee('Saging Saba');
        $this->get(route('products.index'))->assertDontSee('Saging Saba');
        $this->get(route('admin.products.index', ['category' => 'fruit']))->assertSee('Saging Saba');
        $this->get(route('admin.products.index'))->assertDontSee('Saging Saba');
        $this->get(route('admin.products.edit', $saba))->assertSee('<option value="fruit" selected>', false);
    }

    public function test_veggies_page_leaves_out_fruits(): void
    {
        $this->makeProduct('Fresh Carrots');
        $this->makeFruit('Red Apples');

        $response = $this->actingAs($this->admin)->get(route('admin.products.index'));

        $response->assertSee('Fresh Carrots')
            ->assertDontSee('Red Apples');
    }

    public function test_fruits_page_lists_only_fruits(): void
    {
        $this->makeProduct('Fresh Carrots');
        $this->makeFruit('Red Apples');

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['category' => 'fruit']));

        $response->assertSeeText('Add fruit')
            ->assertSee('Red Apples')
            ->assertDontSee('Fresh Carrots');
    }

    public function test_unknown_product_page_is_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['category' => 'meat']))
            ->assertNotFound();
    }

    public function test_side_menu_marks_fruits_while_editing_a_fruit(): void
    {
        $apples = $this->makeFruit('Red Apples');

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $apples));

        $response->assertSeeInOrder(['<span>Veggies</span>', 'aria-current="page"', '<span>Fruits</span>'], false);
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_new_fruit_form_starts_on_the_fruits_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.create', ['category' => 'fruit']))
            ->assertSeeText('Add a fruit')
            ->assertSee('<option value="fruit" selected>', false);
    }

    public function test_adding_a_fruit_returns_to_the_fruits_page(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fuji Apples',
                'category' => 'fruit',
                'variants' => [['label' => '1 piece', 'price' => '35']],
            ])
            ->assertRedirect(route('admin.products.index', ['category' => 'fruit']))
            ->assertSessionHas('status', 'Fuji Apples is now live on the store.');

        $this->assertSame(Product::CATEGORY_FRUIT, Product::where('name', 'Fuji Apples')->value('category'));
    }

    public function test_store_page_must_be_vegetables_or_fruits(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
                'category' => 'meat',
                'variants' => [['label' => 'per kg', 'price' => '95']],
            ])
            ->assertSessionHasErrors('category');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
                'variants' => [['label' => 'per kg', 'price' => '95']],
            ])
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Product::count());
    }

    public function test_new_veggie_form_starts_on_the_vegetables_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('<option value="vegetable" selected>', false);
    }

    public function test_price_list_to_print_shows_each_veggies_photo_name_and_prices(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);
        $carrots->forceFill(['note' => 'Baguio', 'image_path' => 'products/carrots.jpg'])->save();
        $this->makeProduct('Fresh Garlic', 2200, 250, 1);
        $this->makeFruit('Red Apples');

        $this->actingAs($this->admin)
            ->get(route('admin.products.print'))
            ->assertOk()
            ->assertSeeInOrder([
                route('product-photos.show', 'products/carrots.jpg'),
                'Fresh Carrots',
                '10 kg bag - <strong>₱700</strong>',
                'per kg - <strong>₱110</strong>',
                'Fresh Garlic',
                '10 kg bag - <strong>₱2,200</strong>',
            ], false)
            ->assertDontSee('Baguio')
            ->assertDontSee('Red Apples')
            // Lazy photos below the fold would come out blank on paper.
            ->assertDontSee('loading="lazy"', false);
    }

    public function test_price_list_to_print_can_show_the_fruits(): void
    {
        $this->makeProduct('Fresh Carrots');
        $this->makeFruit('Red Apples');

        $this->actingAs($this->admin)
            ->get(route('admin.products.print', ['category' => 'fruit']))
            ->assertOk()
            ->assertSee('Red Apples')
            ->assertDontSee('Fresh Carrots');
    }

    public function test_price_list_pdf_is_named_after_the_page_and_todays_date_in_manila(): void
    {
        // 7:30 AM on October 3 in Manila is still October 2 in UTC.
        $this->travelTo(CarbonImmutable::parse('2026-10-03 07:30', 'Asia/Manila'));
        $this->makeProduct('Fresh Carrots');
        $this->makeFruit('Red Apples');

        $this->actingAs($this->admin)
            ->get(route('admin.products.print'))
            ->assertSeeText('Download PDF')
            ->assertSee('price-list-vegetables-2026-10-03.pdf');

        $this->get(route('admin.products.print', ['category' => 'fruit']))
            ->assertSee('price-list-fruits-2026-10-03.pdf');
    }

    public function test_admin_can_drag_veggies_into_a_new_order_that_the_store_follows(): void
    {
        $okra = $this->makeProduct('Fresh Okra', sortOrder: 0);
        $beans = $this->makeProduct('Snap Beans', sortOrder: 1);
        $carrots = $this->makeProduct('Fresh Carrots', sortOrder: 2);
        $apples = $this->makeFruit('Red Apples');
        $lastChanged = $carrots->updated_at;
        $this->travel(2)->hours();

        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertSee('aria-label="Move Snap Beans"', false);

        $this->patchJson(route('admin.products.order.update'), [
            'category' => 'vegetable',
            'ids' => [$beans->id, $carrots->id, $okra->id],
        ])
            ->assertOk()
            ->assertJson(['message' => 'New order saved. The store shows it now.']);

        $this->get(route('products.index'))->assertSeeInOrder(['Snap Beans', 'Fresh Carrots', 'Fresh Okra']);
        $this->get(route('admin.products.index'))->assertSeeInOrder(['Snap Beans', 'Fresh Carrots', 'Fresh Okra']);
        $this->get(route('admin.products.print'))->assertSeeInOrder(['Snap Beans', 'Fresh Carrots', 'Fresh Okra']);
        // Fruits keep their own order, and moving a veggie doesn't count as changing it.
        $this->assertSame(0, $apples->refresh()->sort_order);
        $this->assertEquals($lastChanged, $carrots->refresh()->updated_at);
    }

    public function test_new_order_must_list_exactly_the_products_on_that_page(): void
    {
        $okra = $this->makeProduct('Fresh Okra', sortOrder: 0);
        $beans = $this->makeProduct('Snap Beans', sortOrder: 1);
        $apples = $this->makeFruit('Red Apples');
        $changed = 'The list changed since this page was opened. Reload the page and try again.';
        $this->actingAs($this->admin);

        // Okra was added in another tab after the page was opened.
        $this->patchJson(route('admin.products.order.update'), ['category' => 'vegetable', 'ids' => [$beans->id]])
            ->assertJsonValidationErrors(['ids' => $changed]);
        // A fruit can't be put among the veggies.
        $this->patchJson(route('admin.products.order.update'), ['category' => 'vegetable', 'ids' => [$beans->id, $okra->id, $apples->id]])
            ->assertJsonValidationErrors(['ids' => $changed]);
        $this->patchJson(route('admin.products.order.update'), ['category' => 'vegetable', 'ids' => [$beans->id, $beans->id]])
            ->assertJsonValidationErrors('ids.0');
        $this->patchJson(route('admin.products.order.update'), ['category' => 'meat', 'ids' => [$beans->id, $okra->id]])
            ->assertJsonValidationErrors('category');

        $this->assertSame([0, 1], [$okra->refresh()->sort_order, $beans->refresh()->sort_order]);
    }

    public function test_non_admins_cannot_change_the_order(): void
    {
        $okra = $this->makeProduct('Fresh Okra', sortOrder: 0);
        $beans = $this->makeProduct('Snap Beans', sortOrder: 1);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.products.order.update'), ['category' => 'vegetable', 'ids' => [$beans->id, $okra->id]])
            ->assertForbidden();

        $this->assertSame(0, $okra->refresh()->sort_order);
    }

    public function test_non_admins_cannot_see_the_price_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.print'))
            ->assertForbidden();
    }

    public function test_admin_can_update_many_prices_at_once(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);
        $garlic = $this->makeProduct('Fresh Garlic', 2200, 250, 1);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.prices.update'), [
                'prices' => [
                    $carrots->id => ['720', '115'],
                    $garlic->id => ['2200', '250'],
                ],
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('status', 'Updated prices for 1 veggie. The store shows them now.');

        $this->assertSame([
            ['label' => '10 kg bag', 'price' => 720],
            ['label' => 'per kg', 'price' => 115],
        ], $carrots->refresh()->variants);
        $this->assertSame(2200, $garlic->refresh()->variants[0]['price']);

        $this->get(route('products.index'))->assertSee('₱720')->assertDontSee('₱700');
    }

    public function test_prices_saved_on_the_fruits_page_return_there(): void
    {
        $apples = $this->makeFruit('Red Apples');

        $this->actingAs($this->admin)
            ->patch(route('admin.products.prices.update'), [
                'category' => 'fruit',
                'prices' => [$apples->id => ['650', '40']],
            ])
            ->assertRedirect(route('admin.products.index', ['category' => 'fruit']))
            ->assertSessionHas('status', 'Updated prices for 1 fruit. The store shows them now.');

        $this->assertSame(40, $apples->refresh()->variants[1]['price']);
    }

    public function test_bulk_price_update_rejects_invalid_prices(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.prices.update'), [
                'prices' => [$carrots->id => ['', '-5']],
            ])
            ->assertSessionHasErrors(["prices.{$carrots->id}.0", "prices.{$carrots->id}.1"]);

        $this->assertSame(700, $carrots->refresh()->variants[0]['price']);
    }

    public function test_non_admins_cannot_change_prices(): void
    {
        $carrots = $this->makeProduct('Fresh Carrots', 700, 110);

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.products.prices.update'), [
                'prices' => [$carrots->id => ['1', '1']],
            ])
            ->assertForbidden();

        $this->assertSame(700, $carrots->refresh()->variants[0]['price']);
    }

    public function test_admin_can_add_a_veggie_with_a_photo_that_shows_on_the_store(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
                'category' => 'vegetable',
                'variants' => [['label' => 'per kg', 'price' => '95']],
                'image' => $this->photo('okra.png'),
            ])
            ->assertRedirect(route('admin.products.index'));

        $okra = Product::where('name', 'Fresh Okra')->firstOrFail();

        $this->assertNotNull($okra->image_path);
        $this->publicDisk()->assertExists($okra->image_path);

        $this->get(route('products.index'))->assertSee($okra->imageUrl(), false);
        $this->get($okra->imageUrl())->assertOk();
    }

    public function test_photo_route_only_serves_existing_product_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret.txt', 'nope');

        $this->get('/product-photos/products/missing.png')->assertNotFound();
        $this->get('/product-photos/secret.txt')->assertNotFound();
        $this->get('/product-photos/products/..%2Fsecret.txt')->assertNotFound();
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct('Fresh Carrots');
        $oldPath = $this->photo()->store('products', 'public');
        $product->forceFill(['image_path' => $oldPath])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Fresh Carrots',
                'category' => 'vegetable',
                'variants' => $product->variants,
                'image' => $this->photo('new-carrots.png'),
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();

        $this->assertNotSame($oldPath, $product->image_path);
        $this->publicDisk()->assertMissing($oldPath);
        $this->publicDisk()->assertExists($product->image_path);
    }

    public function test_admin_can_remove_a_photo(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct('Fresh Carrots');
        $path = $this->photo()->store('products', 'public');
        $product->forceFill(['image_path' => $path])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Fresh Carrots',
                'category' => 'vegetable',
                'variants' => $product->variants,
                'remove_image' => '1',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertNull($product->refresh()->image_path);
        $this->publicDisk()->assertMissing($path);
    }

    public function test_saving_without_a_new_photo_keeps_the_current_one(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct('Fresh Carrots');
        $path = $this->photo()->store('products', 'public');
        $product->forceFill(['image_path' => $path])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Fresh Carrots',
                'category' => 'vegetable',
                'variants' => $product->variants,
                'remove_image' => '0',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame($path, $product->refresh()->image_path);
        $this->publicDisk()->assertExists($path);
    }

    public function test_photo_must_be_an_image(): void
    {
        Storage::fake('public');

        // A real temp file (not a testing fake), so its type is detected from the contents like a real upload.
        $textFile = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($textFile, 'not really a photo');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
                'category' => 'vegetable',
                'variants' => [['label' => 'per kg', 'price' => '95']],
                'image' => new UploadedFile($textFile, 'okra.jpg', 'image/jpeg', null, true),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Product::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_admin_can_delete_a_veggie_and_its_photo(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct('Fresh Carrots');
        $path = $this->photo()->store('products', 'public');
        $product->forceFill(['image_path' => $path])->save();

        $this->actingAs($this->admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertModelMissing($product);
        $this->publicDisk()->assertMissing($path);
        $this->get(route('products.index'))->assertDontSee('Fresh Carrots');
    }

    public function test_deleting_a_veggie_keeps_past_orders_intact(): void
    {
        $product = $this->makeProduct('Fresh Carrots');
        $order = Order::create([
            'full_name' => 'Juan Dela Cruz',
            'contact_number' => '09171234567',
            'delivery_address' => 'Brgy 1',
            'preferred_date' => now()->toDateString(),
            'preferred_time' => '09:00',
            'payment_method' => 'Cash on Delivery',
            'subtotal' => 700,
            'delivery_fee' => 0,
            'total' => 700,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Fresh Carrots',
            'variant_label' => '10 kg bag',
            'unit_price' => 700,
            'qty' => 1,
            'line_total' => 700,
        ]);

        $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product));

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('Fresh Carrots', $item->product_name);
    }

    public function test_non_admins_cannot_delete_a_veggie(): void
    {
        $product = $this->makeProduct('Fresh Carrots');

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.products.destroy', $product))
            ->assertForbidden();

        $this->assertModelExists($product);
    }
}
