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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_daily_expenses_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_daily_expenses_freeze` BEFORE UPDATE ON `daily_expenses` FOR EACH ROW
            BEGIN
                IF NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.expense_category_id <=> OLD.expense_category_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.description <=> OLD.description ) OR NOT ( NEW.cash_account_id <=> OLD.cash_account_id ) OR NOT ( NEW.occurred_at <=> OLD.occurred_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.amount_idr <=> OLD.amount_idr ) OR NOT ( NEW.method <=> OLD.method ) OR NOT ( NEW.reference <=> OLD.reference ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.version = OLD.version + 1 ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_daily_expenses_no_delete`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_daily_expenses_no_delete` BEFORE DELETE ON `daily_expenses` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher cannot be deleted';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_daily_expenses_reverse`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_daily_expenses_reverse` BEFORE UPDATE ON `daily_expenses` FOR EACH ROW
            BEGIN
                IF NEW.status = 'reversed' THEN
                IF NOT EXISTS ( SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id = e.id WHERE e.daily_expense_id = OLD.id ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher requires cash reversal';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_daily_expenses_start`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_daily_expenses_start` BEFORE INSERT ON `daily_expenses` FOR EACH ROW
            BEGIN
                IF NEW.status != 'posted' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher must start posted';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
