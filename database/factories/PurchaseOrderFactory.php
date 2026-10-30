<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DailyOrderSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->state(['is_admin' => true]),
            'purchaser_id' => User::factory()->purchaser(),
            'purchase_date' => DailyOrderSummary::today(),
            'status' => PurchaseOrder::STATUS_ASSIGNED,
        ];
    }
}
