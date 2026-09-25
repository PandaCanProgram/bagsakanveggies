<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * Accent colors keyed by a keyword found in the product name.
     *
     * @var array<string, string>
     */
    protected const SWATCHES = [
        'carrot' => '#e07b24',
        'sayote' => '#9dbf5b',
        'cabbage' => '#86b26b',
        'onion' => '#9c4a5f',
        'tomato' => '#d6452f',
        'eggplant' => '#5e3b6e',
        'ampalaya' => '#4b8b3b',
        'bell pepper' => '#c8402e',
        'celery' => '#8db35a',
        'cucumber' => '#3e7c3f',
        'lettuce' => '#74b04e',
        'lemon' => '#e7c53a',
    ];

    protected const DEFAULT_SWATCH = '#6b8f5e';

    protected $fillable = ['name', 'note', 'variants', 'sort_order'];

    protected $casts = [
        'variants' => 'array',
    ];

    public function variant(int $index): ?array
    {
        return $this->variants[$index] ?? null;
    }

    /**
     * Accent color used to tint this product's card and cart line.
     */
    public function swatchColor(): string
    {
        $name = strtolower($this->name);

        foreach (self::SWATCHES as $keyword => $color) {
            if (str_contains($name, $keyword)) {
                return $color;
            }
        }

        return self::DEFAULT_SWATCH;
    }

    /**
     * Effective price per kilo for a sized variant (e.g. "10 kg bag", "per .5kg", "1/2 kg"),
     * or null when it's already priced per kilo or has no size.
     */
    public function pricePerKilo(int $index): ?float
    {
        $variant = $this->variant($index);

        if (! $variant || ! preg_match('/(?:(\d+)\s*\/\s*(\d+)|(\d*\.?\d+))\s*(kg|kilos?|g|grams?)\b/i', $variant['label'], $matches)) {
            return null;
        }

        $kilos = $matches[1] !== ''
            ? (float) $matches[1] / max((float) $matches[2], 1)
            : (float) $matches[3];

        if (str_starts_with(strtolower($matches[4]), 'g')) {
            $kilos /= 1000;
        }

        return $kilos > 0 && $kilos != 1 ? $variant['price'] / $kilos : null;
    }

    /**
     * Public URL of the photo uploaded from the admin page, or null when there is none.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}
