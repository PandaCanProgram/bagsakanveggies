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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['facebook_id', 'facebook_name', 'facebook_avatar_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('facebook_id')->nullable()->after('contact_number');
            $table->string('facebook_name')->nullable()->after('facebook_id');
            $table->string('facebook_avatar_url')->nullable()->after('facebook_name');
        });
    }
};
