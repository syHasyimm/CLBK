<?php

namespace App\Domain\Shared;

final class Proportion
{
    public static function round(int $amount, int $part, int $whole): int
    {
        if ($amount < 0 || $part < 0 || $whole <= 0 || $part > $whole || $whole > 1000000000) {
            throw new \InvalidArgumentException('Invalid integer allocation.');
        }
        // Split first: the largest multiplication is at most 10^18, below int64.
        $product = ($amount % $whole) * $part;

        return intdiv($amount, $whole) * $part + intdiv($product, $whole) + (($product % $whole) * 2 >= $whole ? 1 : 0);
    }
}
