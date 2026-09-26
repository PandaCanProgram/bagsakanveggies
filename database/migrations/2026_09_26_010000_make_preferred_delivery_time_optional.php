<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout no longer asks for a preferred date and time (dispatch follows the
 * 10 AM cut-off instead), so new orders leave them empty; older orders keep theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('preferred_date')->nullable()->change();
            $table->time('preferred_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('preferred_date')->nullable(false)->change();
            $table->time('preferred_time')->nullable(false)->change();
        });
    }
};
