<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('particulars', 200);
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 40);
            $table->decimal('estimated_unit_price', 12, 2);
            $table->decimal('actual_unit_price', 12, 2)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('purchaser_remarks', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
