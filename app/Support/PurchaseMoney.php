<?php

namespace App\Support;

class PurchaseMoney
{
    public static function cents(string|int $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function lineTotal(string $quantity, string $unitPrice): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $thousandths = ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');

        return intdiv(($thousandths * self::cents($unitPrice)) + 500, 1000);
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign."\u{20B1}".number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
