<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-kilo sizes can be ordered in half kilos (0.5, 1.5, …), so quantities
 * and money amounts need decimals: ½ kg × ₱115 = ₱57.50.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('qty', 8, 1)->change();
            $table->decimal('line_total', 10, 2)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->change();
            $table->decimal('delivery_fee', 10, 2)->default(0)->change();
            $table->decimal('total', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('qty')->change();
            $table->unsignedInteger('line_total')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->change();
            $table->unsignedInteger('delivery_fee')->default(0)->change();
            $table->unsignedInteger('total')->change();
        });
    }
};
