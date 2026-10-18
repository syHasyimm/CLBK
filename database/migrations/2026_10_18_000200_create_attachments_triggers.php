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

        DB::unprepared('DROP TRIGGER IF EXISTS `attachment_source_guard`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `attachment_source_guard` BEFORE INSERT ON `attachments` FOR EACH ROW
            BEGIN
                IF NEW.entity_type = 'factory' AND NOT EXISTS ( SELECT 1 FROM factory_sales WHERE id = NEW.entity_id ) OR NEW.entity_type = 'sort' AND NOT EXISTS ( SELECT 1 FROM sort_sales WHERE id = NEW.entity_id ) OR NEW.entity_type = 'customer' AND NOT EXISTS ( SELECT 1 FROM customer_payments WHERE id = NEW.entity_id ) OR NEW.entity_type = 'payroll' AND NOT EXISTS ( SELECT 1 FROM payroll_payments WHERE id = NEW.entity_id ) OR NEW.entity_type = 'receipt' AND NOT EXISTS ( SELECT 1 FROM sale_receipts WHERE id = NEW.entity_id ) OR NEW.entity_type = 'expense' AND NOT EXISTS ( SELECT 1 FROM daily_expenses WHERE id = NEW.entity_id ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing attachment source';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `attachments_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `attachments_no_DELETE` BEFORE DELETE ON `attachments` FOR EACH ROW
            BEGIN
                IF OLD.entity_type NOT IN ( 'factory' , 'sort' ) OR OLD.entity_type = 'factory' AND ( SELECT status FROM factory_sales WHERE id = OLD.entity_id ) != 'draft' OR OLD.entity_type = 'sort' AND ( SELECT status FROM sort_sales WHERE id = OLD.entity_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted proof cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `attachments_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `attachments_no_UPDATE` BEFORE UPDATE ON `attachments` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proof metadata is immutable';
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
