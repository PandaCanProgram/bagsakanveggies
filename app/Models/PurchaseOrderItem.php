<?php

namespace App\Models;

use App\Support\PurchaseMoney;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected $fillable = ['particulars', 'quantity', 'unit', 'estimated_unit_price', 'remarks'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'estimated_unit_price' => 'decimal:2',
            'actual_unit_price' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function estimatedTotalCents(): int
    {
        return PurchaseMoney::lineTotal($this->quantity, $this->estimated_unit_price);
    }

    public function actualTotalCents(): ?int
    {
        return $this->actual_unit_price === null ? null : PurchaseMoney::lineTotal($this->quantity, $this->actual_unit_price);
    }
}
