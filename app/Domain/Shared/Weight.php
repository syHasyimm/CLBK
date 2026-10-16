<?php

namespace App\Domain\Shared;

use Illuminate\Validation\ValidationException;

final class Weight
{
    public static function grams(string $value, string $field): int
    {
        if (! preg_match('/\A(0|[1-9][0-9]{0,6})(?:[.,]([0-9]{1,3}))?\z/', $value, $parts)) {
            throw ValidationException::withMessages([$field => 'Gunakan kg tanpa pemisah ribuan, maksimal 3 angka desimal.']);
        }
        $grams = (int) $parts[1] * 1000 + (int) str_pad($parts[2] ?? '', 3, '0');
        if ($grams > 1000000000) {
            throw ValidationException::withMessages([$field => 'Berat maksimal 1.000.000 kg.']);
        }

        return $grams;
    }

    public static function kg(int $grams): string
    {
        return intdiv($grams, 1000).'.'.str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT);
    }
}
