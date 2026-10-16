<?php

namespace App\Domain\Shared;

final class Money
{
    public static function forWeight(int $grams, int $rate): int
    {
        // Batas input menjaga perkalian di bawah batas integer 64 bit.
        return intdiv($grams * $rate + 500, 1000);
    }
}
