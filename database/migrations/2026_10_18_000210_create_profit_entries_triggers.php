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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_expense_profit`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_expense_profit` BEFORE INSERT ON `profit_entries` FOR EACH ROW
            BEGIN
                IF NEW.daily_expense_id IS NOT NULL THEN
                IF NEW.category != 'operating_expense' OR NOT EXISTS ( SELECT 1 FROM daily_expenses e WHERE e.id = NEW.daily_expense_id AND e.status = 'posted' AND e.amount_idr = NEW.amount_delta_idr AND ( e.trip_id <=> NEW.trip_id ) AND e.occurred_at = NEW.occurred_at AND e.business_date = NEW.business_date ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expense profit must match voucher';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `profit_entries_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `profit_entries_no_DELETE` BEFORE DELETE ON `profit_entries` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `profit_entries_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `profit_entries_no_UPDATE` BEFORE UPDATE ON `profit_entries` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `profit_reversal_guard`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `profit_reversal_guard` BEFORE INSERT ON `profit_entries` FOR EACH ROW
            BEGIN
                IF NEW.reversal_of_id IS NOT NULL THEN
                IF NOT EXISTS ( SELECT 1 FROM profit_entries p WHERE p.id = NEW.reversal_of_id AND p.reversal_of_id IS NULL AND NEW.amount_delta_idr = - p.amount_delta_idr AND NEW.category = p.category AND ( NEW.trip_id <=> p.trip_id ) AND NEW.occurred_at >= p.occurred_at ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid profit reversal';
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
