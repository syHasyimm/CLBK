<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

final class MySqlDatabase
{
    private const REQUIRED_TRIGGERS = [
        'attachment_source_guard',
        'attachments_no_DELETE',
        'attachments_no_UPDATE',
        'audit_logs_no_delete',
        'audit_logs_no_update',
        'departure_reconciliation',
        'dispatched_trip_costs_frozen',
        'factory_price_schedules_no_DELETE',
        'factory_price_schedules_no_UPDATE',
        'factory_returns_no_DELETE',
        'factory_returns_no_UPDATE',
        'factory_sales_freeze',
        'factory_sales_no_delete',
        'factory_sales_protect_proof',
        'factory_sales_start_draft',
        'finance_cash_balance',
        'finance_cash_customer',
        'finance_cash_entries_no_DELETE',
        'finance_cash_entries_no_UPDATE',
        'finance_cash_expense',
        'finance_cash_payroll',
        'finance_cash_receipt',
        'finance_customer_payment_allocations_guard',
        'finance_customer_payment_allocations_no_DELETE',
        'finance_customer_payment_allocations_no_UPDATE',
        'finance_customer_payments_freeze',
        'finance_customer_payments_no_delete',
        'finance_customer_payments_reverse',
        'finance_customer_payments_start',
        'finance_customer_reconcile',
        'finance_daily_expenses_freeze',
        'finance_daily_expenses_no_delete',
        'finance_daily_expenses_reverse',
        'finance_daily_expenses_start',
        'finance_expense_profit',
        'finance_factory_sale_dependencies',
        'finance_payroll_payment_allocations_guard',
        'finance_payroll_payment_allocations_no_DELETE',
        'finance_payroll_payment_allocations_no_UPDATE',
        'finance_payroll_payments_freeze',
        'finance_payroll_payments_no_delete',
        'finance_payroll_payments_reverse',
        'finance_payroll_payments_start',
        'finance_payroll_reconcile',
        'finance_receipt_source',
        'finance_sale_receipts_freeze',
        'finance_sale_receipts_no_delete',
        'finance_sale_receipts_reverse',
        'finance_sale_receipts_start',
        'finance_sort_sale_dependencies',
        'finance_weighing_dependencies',
        'freeze_posted_weighing',
        'idempotency_requests_no_DELETE',
        'idempotency_requests_no_UPDATE',
        'no_delete_posted_weighing',
        'price_schedules_no_DELETE',
        'price_schedules_no_UPDATE',
        'profit_entries_no_DELETE',
        'profit_entries_no_UPDATE',
        'profit_reversal_guard',
        'reconcile_factory_post',
        'reconcile_sort_post',
        'reconcile_weighing_on_post',
        'sale_items_freeze_DELETE',
        'sale_items_freeze_INSERT',
        'sale_items_freeze_UPDATE',
        'sale_items_freeze_move',
        'sale_receipts_automatic_frozen',
        'sort_lot_frozen',
        'sort_sales_freeze',
        'sort_sales_no_delete',
        'sort_sales_protect_proof',
        'sort_sales_start_draft',
        'sort_stock_movements_no_DELETE',
        'sort_stock_movements_no_UPDATE',
        'stock_movement_guard',
        'vehicle_workflow_factory_price_INSERT',
        'vehicle_workflow_factory_price_UPDATE',
        'vehicle_workflow_factory_return_INSERT',
        'vehicle_workflow_factory_return_UPDATE',
        'vehicle_workflow_factory_sales_INSERT',
        'vehicle_workflow_factory_sales_UPDATE',
        'vehicle_workflow_factory_sales_freeze',
        'vehicle_workflow_factory_sales_post',
        'vehicle_workflow_sort_item_INSERT',
        'vehicle_workflow_sort_item_UPDATE',
        'vehicle_workflow_sort_lot_INSERT',
        'vehicle_workflow_sort_lot_UPDATE',
        'vehicle_workflow_sort_sales_INSERT',
        'vehicle_workflow_sort_sales_UPDATE',
        'vehicle_workflow_sort_sales_freeze',
        'vehicle_workflow_sort_sales_post',
        'vehicle_workflow_weighings_INSERT',
        'vehicle_workflow_weighings_UPDATE',
        'vehicle_workflow_weighings_freeze',
        'weighing_after_departure_delete',
        'weighing_after_departure_insert',
        'weighing_after_departure_update',
        'weighing_items_freeze_DELETE',
        'weighing_items_freeze_INSERT',
        'weighing_items_freeze_UPDATE',
        'weighing_items_freeze_move',
        'weighing_loaders_freeze_DELETE',
        'weighing_loaders_freeze_INSERT',
        'weighing_loaders_freeze_UPDATE',
        'weighing_loaders_freeze_move',
        'weighings_must_start_as_draft',
    ];

    public function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    public function tables(): array
    {
        return DB::connection()->getPdo()->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function columns(string $table): array
    {
        return DB::connection()->getPdo()->query('SHOW COLUMNS FROM '.$this->quote($table))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function foreignKeys(): array
    {
        return DB::connection()->getPdo()->query('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertVersion(): void
    {
        if (! preg_match('/\A8\.4\./', (string) DB::selectOne('SELECT VERSION() AS version')->version)) {
            throw new RuntimeException('Aplikasi ini memerlukan MySQL 8.4.');
        }
    }

    public function guardsPresent(): bool
    {
        $actual = DB::connection()->getPdo()->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);

        return array_diff(self::REQUIRED_TRIGGERS, $actual) === []
            && DB::table('database_write_locks')->where('id', 1)->exists();
    }

    public function integrity(): bool
    {
        $engines = DB::selectOne("SELECT COUNT(*) AS invalid FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB'");
        if ((int) $engines->invalid !== 0) {
            return false;
        }
        $constraints = [];
        foreach ($this->foreignKeys() as $key) {
            $constraints[$key['TABLE_NAME'].'.'.$key['CONSTRAINT_NAME']][] = $key;
        }
        foreach ($constraints as $keys) {
            $table = $this->quote($keys[0]['TABLE_NAME']);
            $reference = $this->quote($keys[0]['REFERENCED_TABLE_NAME']);
            $notNull = implode(' AND ', array_map(fn ($key) => 'c.'.$this->quote($key['COLUMN_NAME']).' IS NOT NULL', $keys));
            $join = implode(' AND ', array_map(fn ($key) => 'p.'.$this->quote($key['REFERENCED_COLUMN_NAME']).'=c.'.$this->quote($key['COLUMN_NAME']), $keys));
            if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM {$table} c WHERE {$notNull} AND NOT EXISTS(SELECT 1 FROM {$reference} p WHERE {$join})) AS invalid")->invalid) {
                return false;
            }
        }

        return true;
    }
}
