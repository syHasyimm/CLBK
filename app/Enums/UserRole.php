<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Driver = 'driver';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manajer Toke',
            self::Driver => 'Supir',
            self::Cashier => 'Kasir',
        };
    }
}
