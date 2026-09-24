<?php

namespace App\Models;

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
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function messengerText(): string
    {
        $itemLines = $this->items->map(function (OrderItem $item) {
            return "- {$item->product_name} ({$item->variant_label}) x{$item->qty} = ₱".number_format($item->line_total);
        })->implode("\n");

        $when = $this->preferred_date->format('M j, Y').' '.\Carbon\Carbon::parse($this->preferred_time)->format('g:i A');

        $text = "Hi! Confirming Order #{$this->id}:\n{$itemLines}\n\n"
            ."Total: ₱".number_format($this->total)."\n"
            ."Name: {$this->full_name}\n"
            ."Contact #: {$this->contact_number}\n"
            ."Delivery: {$this->delivery_address}\n"
            ."Preferred: {$when}";

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
