<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The live store already has the client's products when this migration runs, so it may only add
 * the category: every product keeps its name, prices, photo and order, and stays on the store.
 */
class AddCategoryToProductsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_10_01_165025_add_category_to_products_table.php');
    }

    /**
     * Insert products the way the live store has them before the migration (no category column).
     *
     * @param  list<string>  $names
     */
    protected function insertLiveProducts(array $names): void
    {
        foreach ($names as $index => $name) {
            DB::table('products')->insert([
                'name' => $name,
                'note' => null,
                'variants' => json_encode([['label' => '1 kg', 'price' => 70 + $index], ['label' => '200 grams', 'price' => 20]]),
                'image_path' => "products/photo-{$index}.jpg",
                'sort_order' => $index,
                'created_at' => '2026-09-29 00:56:16',
                'updated_at' => '2026-10-01 00:30:58',
            ]);
        }
    }

    public function test_existing_products_keep_every_detail_and_only_fruits_move(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->insertLiveProducts([
            'Fresh Carrots (On Sale!)',
            'Saging Saba',
            'Red Apples (Priced per piece)',
            'Fuji Apples',
            'Orange',
            'Fresh Lemon',
            'Fresh Papaya',
            'Fresh Sili Labuyo',
        ]);
        $before = DB::table('products')->orderBy('id')->get()->map(fn (object $product): array => (array) $product)->all();

        $migration->up();

        $after = DB::table('products')->orderBy('id')->get();

        $this->assertSame($before, $after->map(fn (object $product): array => Arr::except((array) $product, 'category'))->all());
        $this->assertSame([
            'Fresh Carrots (On Sale!)' => 'vegetable',
            'Saging Saba' => 'fruit',
            'Red Apples (Priced per piece)' => 'fruit',
            'Fuji Apples' => 'fruit',
            'Orange' => 'fruit',
            'Fresh Lemon' => 'vegetable',
            'Fresh Papaya' => 'vegetable',
            'Fresh Sili Labuyo' => 'vegetable',
        ], $after->pluck('category', 'name')->all());
    }

    public function test_rolling_back_removes_only_the_category(): void
    {
        $this->insertLiveProducts(['Fresh Carrots', 'Fuji Apples']);

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('products', 'category'));
        $this->assertSame(['Fresh Carrots', 'Fuji Apples'], DB::table('products')->orderBy('id')->pluck('name')->all());
    }
}
