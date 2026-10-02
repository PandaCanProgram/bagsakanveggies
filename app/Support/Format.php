<?php

namespace App\Support;

class Format
{
    /**
     * Peso amount, showing centavos only when there are any: ₱55, ₱57.50, ₱1,400.
     */
    public static function peso(float|int|string|null $amount): string
    {
        $amount = round((float) $amount, 2);
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return '₱'.number_format($amount, $decimals);
    }

    /**
     * Quantity without trailing zeros: 2, 0.5, 1.5 (or 0.25 when asked for more decimals).
     */
    public static function qty(float|int|string $qty, int $decimals = 1): string
    {
        return rtrim(rtrim(number_format((float) $qty, $decimals, '.', ''), '0'), '.');
    }
}
