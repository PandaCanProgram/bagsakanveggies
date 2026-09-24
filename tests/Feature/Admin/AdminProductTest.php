<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
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

    public function test_admin_can_add_a_veggie_that_appears_last_on_the_store(): void
    {
        $this->makeProduct('Fresh Carrots');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Okra',
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
        $this->assertSame(1, $okra->sort_order);

        $this->get(route('products.index'))->assertSeeInOrder(['Fresh Carrots', 'Fresh Okra', '₱450']);
    }

    public function test_adding_a_veggie_requires_a_unique_name_and_valid_prices(): void
    {
        $this->makeProduct('Fresh Carrots');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Fresh Carrots',
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
                'variants' => [['label' => 'per kg', 'price' => '95']],
                'image' => $this->photo('okra.png'),
            ])
            ->assertRedirect(route('admin.products.index'));

        $okra = Product::where('name', 'Fresh Okra')->firstOrFail();

        $this->assertNotNull($okra->image_path);
        Storage::disk('public')->assertExists($okra->image_path);

        $this->get(route('products.index'))->assertSee($okra->imageUrl(), false);
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
                'variants' => $product->variants,
                'image' => $this->photo('new-carrots.png'),
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();

        $this->assertNotSame($oldPath, $product->image_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->image_path);
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
                'variants' => $product->variants,
                'remove_image' => '1',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertNull($product->refresh()->image_path);
        Storage::disk('public')->assertMissing($path);
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
                'variants' => $product->variants,
                'remove_image' => '0',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame($path, $product->refresh()->image_path);
        Storage::disk('public')->assertExists($path);
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
                'variants' => [['label' => 'per kg', 'price' => '95']],
                'image' => new UploadedFile($textFile, 'okra.jpg', 'image/jpeg', null, true),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Product::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
