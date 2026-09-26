<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The client's price sheet labels the retail size "1 kg" instead of "per kg".
 * Past orders keep the label they were placed with.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameSize('per kg', '1 kg');
    }

    public function down(): void
    {
        $this->renameSize('1 kg', 'per kg');
    }

    protected function renameSize(string $from, string $to): void
    {
        foreach (DB::table('products')->get(['id', 'variants']) as $product) {
            $variants = json_decode($product->variants, true) ?: [];
            $changed = false;

            foreach ($variants as &$variant) {
                if (strcasecmp(trim($variant['label'] ?? ''), $from) === 0) {
                    $variant['label'] = $to;
                    $changed = true;
                }
            }
            unset($variant);

            if ($changed) {
                DB::table('products')->where('id', $product->id)->update(['variants' => json_encode($variants)]);
            }
        }
    }
};
