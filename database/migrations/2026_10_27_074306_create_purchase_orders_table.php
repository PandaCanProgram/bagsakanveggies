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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('purchaser_id')->constrained('users')->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('status')->default('assigned');
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['purchaser_id', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
