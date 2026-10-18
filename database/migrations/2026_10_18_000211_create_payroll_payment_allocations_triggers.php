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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_payroll_payment_allocations_guard`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_payroll_payment_allocations_guard` BEFORE INSERT ON `payroll_payment_allocations` FOR EACH ROW
            BEGIN
                IF NOT EXISTS ( SELECT 1 FROM weighing_loaders l JOIN weighings w ON w.id = l.weighing_id JOIN payroll_payments p ON p.id = NEW.payroll_payment_id WHERE l.id = NEW.weighing_loader_id AND l.worker_id = p.worker_id AND w.business_date BETWEEN p.period_start AND p.period_end AND w.status = 'posted' AND p.status = 'posted' AND p.occurred_at >= w.weighed_at ) OR EXISTS ( SELECT 1 FROM cash_entries WHERE payroll_payment_id = NEW.payroll_payment_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( a.amount_idr ) FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id = a.payroll_payment_id WHERE a.weighing_loader_id = NEW.weighing_loader_id AND p.status = 'posted' ) , 0 ) > ( SELECT l.share_amount_idr FROM weighing_loaders l JOIN weighings w ON w.id = l.weighing_id WHERE l.id = NEW.weighing_loader_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM payroll_payment_allocations WHERE payroll_payment_id = NEW.payroll_payment_id ) , 0 ) > ( SELECT amount_idr FROM payroll_payments WHERE id = NEW.payroll_payment_id ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid payment allocation';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_payroll_payment_allocations_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_payroll_payment_allocations_no_DELETE` BEFORE DELETE ON `payroll_payment_allocations` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_payroll_payment_allocations_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_payroll_payment_allocations_no_UPDATE` BEFORE UPDATE ON `payroll_payment_allocations` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
