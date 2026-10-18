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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_customer_payment_allocations_guard`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_customer_payment_allocations_guard` BEFORE INSERT ON `customer_payment_allocations` FOR EACH ROW
            BEGIN
                IF NOT EXISTS ( SELECT 1 FROM weighings w JOIN customer_payments p ON p.id = NEW.customer_payment_id WHERE w.id = NEW.weighing_id AND w.customer_id = p.customer_id AND w.status = 'posted' AND p.status = 'posted' AND p.occurred_at >= w.weighed_at ) OR EXISTS ( SELECT 1 FROM cash_entries WHERE customer_payment_id = NEW.customer_payment_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( a.amount_idr ) FROM customer_payment_allocations a JOIN customer_payments p ON p.id = a.customer_payment_id WHERE a.weighing_id = NEW.weighing_id AND p.status = 'posted' ) , 0 ) > ( SELECT w.total_purchase_idr FROM weighings w WHERE w.id = NEW.weighing_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM customer_payment_allocations WHERE customer_payment_id = NEW.customer_payment_id ) , 0 ) > ( SELECT amount_idr FROM customer_payments WHERE id = NEW.customer_payment_id ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid payment allocation';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_customer_payment_allocations_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_customer_payment_allocations_no_DELETE` BEFORE DELETE ON `customer_payment_allocations` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_customer_payment_allocations_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_customer_payment_allocations_no_UPDATE` BEFORE UPDATE ON `customer_payment_allocations` FOR EACH ROW
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
