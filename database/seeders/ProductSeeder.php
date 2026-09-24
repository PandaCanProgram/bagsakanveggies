<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Fresh Carrots', 'note' => '(Benguet)', 'bag' => 700, 'kg' => 110],
            ['name' => 'Fresh Sayote', 'note' => null, 'bag' => 800, 'kg' => 120],
            ['name' => 'Fresh Cabbage', 'note' => null, 'bag' => 750, 'kg' => 100],
            ['name' => 'Fresh Onions', 'note' => null, 'bag' => 900, 'kg' => 130],
            ['name' => 'Fresh Tomatoes', 'note' => null, 'bag' => 800, 'kg' => 120],
            ['name' => 'Fresh Eggplant', 'note' => null, 'bag' => 700, 'kg' => 110],
            ['name' => 'Fresh Ampalaya', 'note' => null, 'bag' => 800, 'kg' => 120],
            ['name' => 'Fresh Bell Pepper', 'note' => null, 'bag' => 1300, 'kg' => 180],
            ['name' => 'Fresh Celery', 'note' => null, 'bag' => 1800, 'kg' => 220],
            ['name' => 'Fresh Cucumber', 'note' => null, 'bag' => 700, 'kg' => 110],
            ['name' => 'Fresh Lettuce', 'note' => null, 'bag' => 900, 'kg' => 130],
            ['name' => 'Fresh Lemon', 'note' => null, 'bag' => 1200, 'kg' => 160],
        ];

        foreach ($products as $index => $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                [
                    'note' => $product['note'],
                    'variants' => [
                        ['label' => '10 kg bag', 'price' => $product['bag']],
                        ['label' => 'per kg', 'price' => $product['kg']],
                    ],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
