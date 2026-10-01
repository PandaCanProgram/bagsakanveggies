<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function namesAndEmojis(): array
    {
        return [
            'English name with "Fresh" and a plural' => ['Fresh Carrots', '🥕'],
            'Filipino name' => ['Saging Saba', '🍌'],
            'the client\'s spelling of broccoli' => ['Fresh Brocolli', '🥦'],
            'spring onions rather than onions' => ['Fresh Spring Onions', '🌱'],
            'leeks rather than the onion in their name' => ['Fresh Onion Leeks', '🌱'],
            'sweet potato rather than potato' => ['Sweet Potato', '🍠'],
            'pineapple rather than apple' => ['Pineapple', '🍍'],
            'watermelon rather than melon' => ['Watermelon', '🍉'],
            'any letter case' => ['FRESH GARLIC', '🧄'],
            'basket when there is no emoji for it' => ['Fresh Radish', '🧺'],
        ];
    }

    #[DataProvider('namesAndEmojis')]
    public function test_emoji_matches_the_veggie_or_fruit_in_the_name(string $name, string $emoji): void
    {
        $this->assertSame($emoji, Product::emojiFor($name));
    }
}
