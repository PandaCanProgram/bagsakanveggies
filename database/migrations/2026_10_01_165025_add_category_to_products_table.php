<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Splits the store into a Vegetables page and a Fruits page.
 * Every existing product starts on Vegetables, so nothing drops off the store, then the ones
 * the client added as fruits move to Fruits. She can switch any product from its admin page.
 * Only adds a column: names, prices, photos and orders are left exactly as they are.
 */
return new class extends Migration
{
    /**
     * A product whose name contains one of these goes on the Fruits page.
     *
     * @var list<string>
     */
    protected const FRUIT_KEYWORDS = [
        'apple', 'orange', 'saging', 'banana', 'mango', 'grape', 'pineapple', 'watermelon', 'pomelo', 'strawberr',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 20)->default('vegetable')->after('note');
        });

        $fruitIds = DB::table('products')->get(['id', 'name'])
            ->filter(fn (object $product): bool => Str::contains($product->name, self::FRUIT_KEYWORDS, ignoreCase: true))
            ->pluck('id');

        // Query builder update, so "last changed" on the admin page keeps the client's own edit times.
        DB::table('products')->whereIn('id', $fruitIds)->update(['category' => 'fruit']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
