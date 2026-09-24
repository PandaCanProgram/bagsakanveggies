<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'note', 'variants', 'sort_order'];

    protected $casts = [
        'variants' => 'array',
    ];

    public function variant(int $index): ?array
    {
        return $this->variants[$index] ?? null;
    }
}
