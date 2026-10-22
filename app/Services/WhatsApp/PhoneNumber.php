<?php

namespace App\Services\WhatsApp;

final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $number = preg_replace('/[\s().-]+/', '', trim($value ?? ''));
        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        }
        if (str_starts_with($number, '08')) {
            $number = '62'.substr($number, 1);
        }

        return preg_match('/\A628[1-9][0-9]{7,10}\z/', $number) ? $number : null;
    }
}
