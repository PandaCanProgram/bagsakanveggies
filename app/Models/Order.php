<?php

namespace App\Models;

use App\Support\Format;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'full_name',
        'contact_number',
        'delivery_address',
        'preferred_date',
        'preferred_time',
        'order_notes',
        'payment_method',
        'subtotal',
        'delivery_fee',
        'total',
        'status',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'subtotal' => 'float',
        'delivery_fee' => 'float',
        'total' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders placed from midnight to midnight on the given day, in that day's timezone (orders are saved in UTC).
     */
    public function scopePlacedOn(Builder $query, CarbonImmutable $day): void
    {
        $start = $day->startOfDay();
        $appTimezone = config('app.timezone');

        $query->where('created_at', '>=', $start->setTimezone($appTimezone))
            ->where('created_at', '<', $start->addDay()->setTimezone($appTimezone));
    }

    public function messengerText(): string
    {
        $itemLines = $this->items->map(function (OrderItem $item) {
            return "- {$item->product_name} ({$item->variant_label}) x".Format::qty($item->qty).' = '.Format::peso($item->line_total);
        })->implode("\n");

        $text = "Hi! Confirming Order #{$this->id}:\n{$itemLines}\n\n"
            .'Total: '.Format::peso($this->total)."\n"
            ."Payment: GCash\n"
            ."Name: {$this->full_name}\n"
            ."CP or Viber: {$this->contact_number}\n"
            ."Delivery Address: {$this->delivery_address}";

        // Orders placed before checkout stopped asking for a date/time (and notes) still show theirs.
        if ($this->preferred_date && $this->preferred_time) {
            $text .= "\nPreferred: ".$this->preferred_date->format('M j, Y').' '.Carbon::parse($this->preferred_time)->format('g:i A');
        }

        if ($this->order_notes) {
            $text .= "\nNotes: {$this->order_notes}";
        }

        return $text;
    }

    public function messengerUrl(): ?string
    {
        $pageId = config('services.facebook.page_id');

        if (! $pageId) {
            return null;
        }

        return 'https://m.me/'.$pageId.'?text='.urlencode($this->messengerText());
    }
}
