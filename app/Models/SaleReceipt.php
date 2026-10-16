<?php

namespace App\Models;

class SaleReceipt extends FinanceDocument
{
    protected function casts(): array
    {
        return parent::casts() + ['is_automatic' => 'boolean'];
    }
}
