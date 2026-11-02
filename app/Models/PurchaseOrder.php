<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['name', 'created_by', 'purchaser_id', 'purchase_date', 'remarks'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    public function number(): string
    {
        return 'PO-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function displayName(): string
    {
        return filled($this->name) ? $this->name : $this->number();
    }

    public function scopeMatchingSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($search)).'%';

        return $query->where(function (Builder $matches) use ($search, $pattern): void {
            $matches->whereRaw("LOWER(purchase_orders.name) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('purchaser', function (Builder $purchasers) use ($pattern): void {
                    $purchasers->whereRaw("LOWER(users.name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(users.email) LIKE ? ESCAPE '!'", [$pattern]);
                });

            if (preg_match('/^(?:PO[-\s]*)?(\d+)$/i', $search, $reference)) {
                $matches->orWhere('purchase_orders.id', $reference[1]);
            }
        });
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
