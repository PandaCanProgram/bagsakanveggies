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

    /**
     * Emoji shown beside a product on the order summary, keyed by a word in its name (English or Filipino).
     * The first match wins, so names like "spring onion" come before "onion".
     *
     * @var array<string, string>
     */
    protected const EMOJIS = [
        'spring onion' => '🌱',
        'green onion' => '🌱',
        'leek' => '🌱',
        'sweet potato' => '🍠',
        'onion' => '🧅',
        'sibuyas' => '🧅',
        'garlic' => '🧄',
        'bawang' => '🧄',
        'ginger' => '🫚',
        'luya' => '🫚',
        'potato' => '🥔',
        'patatas' => '🥔',
        'kamote' => '🍠',
        'carrot' => '🥕',
        'broc' => '🥦',
        'cauliflower' => '🥦',
        'cabbage' => '🥬',
        'repolyo' => '🥬',
        'wombok' => '🥬',
        'pechay' => '🥬',
        'lettuce' => '🥬',
        'kangkong' => '🥬',
        'celery' => '🌿',
        'tomato' => '🍅',
        'kamatis' => '🍅',
        'eggplant' => '🍆',
        'talong' => '🍆',
        'bell pepper' => '🫑',
        'sili' => '🌶️',
        'chili' => '🌶️',
        'cucumber' => '🥒',
        'pipino' => '🥒',
        'zucchini' => '🥒',
        'ampalaya' => '🥒',
        'patola' => '🥒',
        'upo' => '🥒',
        'sayote' => '🍐',
        'beans' => '🫛',
        'sitaw' => '🫛',
        'okra' => '🫛',
        'kalabasa' => '🎃',
        'squash' => '🎃',
        'corn' => '🌽',
        'mais' => '🌽',
        'mushroom' => '🍄',
        'calamansi' => '🍋',
        'kalamansi' => '🍋',
        'lemon' => '🍋',
        'lime' => '🍋',
        'apple' => '🍎',
        'pineapple' => '🍍',
        'pinya' => '🍍',
        'orange' => '🍊',
        'dalandan' => '🍊',
        'banana' => '🍌',
        'saging' => '🍌',
        'mango' => '🥭',
        'mangga' => '🥭',
        'watermelon' => '🍉',
        'pakwan' => '🍉',
        'melon' => '🍈',
        'papaya' => '🍈',
        'grape' => '🍇',
        'ubas' => '🍇',
        'strawberr' => '🍓',
        'coconut' => '🥥',
        'buko' => '🥥',
        'avocado' => '🥑',
    ];

    /**
     * Shown for produce with no emoji of its own (e.g. radish).
     */
    protected const DEFAULT_EMOJI = '🧺';

    public const CATEGORY_VEGETABLE = 'vegetable';

    public const CATEGORY_FRUIT = 'fruit';

    /**
     * Store pages a product can be on, keyed by category, with the page name.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_VEGETABLE => 'Vegetables',
        self::CATEGORY_FRUIT => 'Fruits',
    ];

    /**
     * What the admin pages call one product of each category.
     *
     * @var array<string, string>
     */
    public const ADMIN_NAMES = [
        self::CATEGORY_VEGETABLE => 'veggie',
        self::CATEGORY_FRUIT => 'fruit',
    ];

    protected $fillable = ['name', 'note', 'category', 'variants', 'sort_order'];

    protected $attributes = [
        'category' => self::CATEGORY_VEGETABLE,
    ];

    protected $casts = [
        'variants' => 'array',
    ];

    public function isFruit(): bool
    {
        return $this->category === self::CATEGORY_FRUIT;
    }

    /**
     * Admin page where a category's products are managed: Veggies at /admin/products, Fruits at ?category=fruit.
     */
    public static function adminListUrl(string $category): string
    {
        return route('admin.products.index', $category === self::CATEGORY_VEGETABLE ? [] : ['category' => $category]);
    }

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
     * Veggie or fruit emoji for a product name: "Fresh Carrots" → 🥕, "Saging Saba" → 🍌.
     * Keywords match at the start of a word, so "Pineapple" isn't taken for an apple.
     */
    public static function emojiFor(string $name): string
    {
        foreach (self::EMOJIS as $keyword => $emoji) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'/iu', $name)) {
                return $emoji;
            }
        }

        return self::DEFAULT_EMOJI;
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

        return $variant && self::kilosPerUnit($variant['label']) == 1;
    }

    /**
     * Kilos in one of a size ("10 kg bag" → 10, "200 grams" → 0.2, "1 kg" or "per kg" → 1),
     * or null when the size isn't sold by weight (e.g. "1 piece").
     */
    public static function kilosPerUnit(string $label): ?float
    {
        return self::kilosIn($label) ?? (preg_match('/\b(kg|kilos?)\b/i', $label) ? 1.0 : null);
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
