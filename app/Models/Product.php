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
        $kilos = $variant ? self::kilosIn($variant['label']) : null;

        return $kilos && $kilos != 1 ? $variant['price'] / $kilos : null;
    }

    /**
     * Per-kilo sizes ("per kg", "1 kg", "kilo") can be ordered in half kilos; bags only whole.
     */
    public function allowsHalf(int $index): bool
    {
        $variant = $this->variant($index);

        if (! $variant) {
            return false;
        }

        $kilos = self::kilosIn($variant['label']);

        return $kilos === null
            ? (bool) preg_match('/\b(kg|kilos?)\b/i', $variant['label'])
            : $kilos == 1;
    }

    /**
     * Smallest amount a size can be ordered in: 0.5 kg for per-kilo sizes, otherwise 1.
     */
    public function minQty(int $index): float
    {
        return $this->allowsHalf($index) ? 0.5 : 1.0;
    }

    /**
     * How finely a size can be ordered: per-kilo sizes to 0.1 kg (0.5, 0.6, 0.7…), bags whole only.
     */
    public function qtyPrecision(int $index): int
    {
        return $this->allowsHalf($index) ? 1 : 0;
    }

    /**
     * Round a typed amount for this size (0.1 kg or whole), at least the minimum, at most 999. 0 stays 0.
     */
    public function normalizeQty(int $index, float $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $qty = round($qty, $this->qtyPrecision($index));

        return min(max($qty, $this->minQty($index)), 999.0);
    }

    /**
     * Kilos in a size label ("10 kg bag" → 10, "per .5kg" → 0.5, "1/2 kg" → 0.5, "500g" → 0.5),
     * or null when the label has no amount (e.g. "per kg").
     */
    protected static function kilosIn(string $label): ?float
    {
        if (! preg_match('/(?:(\d+)\s*\/\s*(\d+)|(\d*\.?\d+))\s*(kg|kilos?|g|grams?)\b/i', $label, $matches)) {
            return null;
        }

        $kilos = $matches[1] !== ''
            ? (float) $matches[1] / max((float) $matches[2], 1)
            : (float) $matches[3];

        if (str_starts_with(strtolower($matches[4]), 'g')) {
            $kilos /= 1000;
        }

        return $kilos > 0 ? $kilos : null;
    }

    /**
     * Public URL of the photo uploaded from the admin page, or null when there is none.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? route('product-photos.show', $this->image_path) : null;
    }
}
