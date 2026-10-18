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

        DB::unprepared('DROP TRIGGER IF EXISTS `sale_items_freeze_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sale_items_freeze_DELETE` BEFORE DELETE ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM sort_sales WHERE id = OLD.sort_sale_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sale_items_freeze_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sale_items_freeze_INSERT` BEFORE INSERT ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM sort_sales WHERE id = NEW.sort_sale_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sale_items_freeze_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sale_items_freeze_UPDATE` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM sort_sales WHERE id = OLD.sort_sale_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sale_items_freeze_move`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sale_items_freeze_move` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF NEW.sort_sale_id != OLD.sort_sale_id AND ( SELECT status FROM sort_sales WHERE id = NEW.sort_sale_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move detail to posted sale';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_item_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_item_INSERT` BEFORE INSERT ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF ( SELECT workflow FROM sort_sales WHERE id = NEW.sort_sale_id ) = 'direct' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sort sales do not allocate FIFO';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_item_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_item_UPDATE` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
            BEGIN
                IF ( SELECT workflow FROM sort_sales WHERE id = NEW.sort_sale_id ) = 'direct' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sort sales do not allocate FIFO';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
