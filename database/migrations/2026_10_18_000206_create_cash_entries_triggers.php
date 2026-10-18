<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptExistingSchema()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_balance`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_balance` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.occurred_at < COALESCE( ( SELECT MAX( occurred_at ) FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) , NEW.occurred_at ) OR COALESCE( ( SELECT SUM( CASE direction WHEN 'in' THEN amount_idr ELSE - amount_idr END ) FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) , 0 ) + ( CASE NEW.direction WHEN 'in' THEN NEW.amount_idr ELSE - NEW.amount_idr END ) NOT BETWEEN 0 AND 1000000000000000 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash balance or chronology invalid';
                END IF;
                IF NEW.kind != 'reversal' AND NOT EXISTS ( SELECT 1 FROM cash_accounts WHERE id = NEW.cash_account_id AND is_active = 1 ) OR NEW.kind IN ( 'opening' , 'capital' , 'withdrawal' , 'reversal' ) AND NOT EXISTS ( SELECT 1 FROM users WHERE id = NEW.created_by AND role = 'manager' AND is_active = 1 ) OR NEW.kind = 'opening' AND EXISTS ( SELECT 1 FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) OR NEW.kind IN ( 'opening' , 'capital' ) AND NEW.direction != 'in' OR NEW.kind = 'withdrawal' AND NEW.direction != 'out' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid manual cash entry';
                END IF;
                IF NEW.kind = 'reversal' AND NOT EXISTS ( SELECT 1 FROM cash_entries e WHERE e.id = NEW.reversal_of_id AND e.kind != 'reversal' AND e.cash_account_id = NEW.cash_account_id AND e.direction != NEW.direction AND e.amount_idr = NEW.amount_idr AND NEW.occurred_at >= e.occurred_at ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid cash reversal';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_customer`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_customer` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'customer' THEN
                IF NEW.customer_payment_id IS NULL OR NOT EXISTS ( SELECT 1 FROM customer_payments p WHERE p.id = NEW.customer_payment_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_entries_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_entries_no_DELETE` BEFORE DELETE ON `cash_entries` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_entries_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_entries_no_UPDATE` BEFORE UPDATE ON `cash_entries` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_expense`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_expense` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'expense' THEN
                IF NEW.daily_expense_id IS NULL OR NOT EXISTS ( SELECT 1 FROM daily_expenses p WHERE p.id = NEW.daily_expense_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_payroll`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_payroll` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'payroll' THEN
                IF NEW.payroll_payment_id IS NULL OR NOT EXISTS ( SELECT 1 FROM payroll_payments p WHERE p.id = NEW.payroll_payment_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_cash_receipt`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_cash_receipt` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'receipt' THEN
                IF NEW.sale_receipt_id IS NULL OR NOT EXISTS ( SELECT 1 FROM sale_receipts p WHERE p.id = NEW.sale_receipt_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'in' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_customer_reconcile`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_customer_reconcile` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'customer' THEN
                IF NOT ( NEW.amount_idr <=> ( SELECT SUM( amount_idr ) FROM customer_payment_allocations WHERE customer_payment_id = NEW.customer_payment_id ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment allocations do not reconcile';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_payroll_reconcile`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_payroll_reconcile` BEFORE INSERT ON `cash_entries` FOR EACH ROW
            BEGIN
                IF NEW.kind = 'payroll' THEN
                IF NOT ( NEW.amount_idr <=> ( SELECT SUM( amount_idr ) FROM payroll_payment_allocations WHERE payroll_payment_id = NEW.payroll_payment_id ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment allocations do not reconcile';
                END IF;
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
