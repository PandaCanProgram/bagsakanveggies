<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['created_by', 'purchaser_id', 'purchase_date', 'remarks'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    public function number(): string
    {
        return 'PO-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function estimatedTotalCents(): int
    {
        return $this->items->sum(fn (PurchaseOrderItem $item): int => $item->estimatedTotalCents());
    }

    public function actualTotalCents(): int
    {
        return $this->items->sum(fn (PurchaseOrderItem $item): int => $item->actualTotalCents() ?? 0);
    }

    public function unpricedItemsCount(): int
    {
        return $this->items->whereNull('actual_unit_price')->count();
    }
}
