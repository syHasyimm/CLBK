<?php

namespace App\Enums;

use App\Models\CustomerPayment;
use App\Models\DailyExpense;
use App\Models\PayrollPayment;
use App\Models\SaleReceipt;

enum FinanceKind: string
{
    case Customer = 'customer';
    case Payroll = 'payroll';
    case Receipt = 'receipt';
    case Expense = 'expense';

    public function model(): string
    {
        return match ($this) {
            self::Customer => CustomerPayment::class,
            self::Payroll => PayrollPayment::class,
            self::Receipt => SaleReceipt::class,
            self::Expense => DailyExpense::class,
        };
    }

    public function table(): string
    {
        return (new ($this->model()))->getTable();
    }

    public function sourceColumn(): string
    {
        return match ($this) {
            self::Customer => 'customer_payment_id', self::Payroll => 'payroll_payment_id',
            self::Receipt => 'sale_receipt_id', self::Expense => 'daily_expense_id',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Pembayaran Konsumen', self::Payroll => 'Gaji Karyawan Muat',
            self::Receipt => 'Penerimaan Penjualan', self::Expense => 'Uang Keluar Harian',
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Customer => 'BAYAR', self::Payroll => 'GAJI', self::Receipt => 'TERIMA', self::Expense => 'BIAYA',
        };
    }
}
