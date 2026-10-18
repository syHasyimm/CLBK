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

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_loaders_freeze_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_loaders_freeze_DELETE` BEFORE DELETE ON `weighing_loaders` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_loaders_freeze_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_loaders_freeze_INSERT` BEFORE INSERT ON `weighing_loaders` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_loaders_freeze_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_loaders_freeze_UPDATE` BEFORE UPDATE ON `weighing_loaders` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_loaders_freeze_move`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_loaders_freeze_move` BEFORE UPDATE ON `weighing_loaders` FOR EACH ROW
            BEGIN
                IF NEW.weighing_id != OLD.weighing_id AND ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move detail to posted weighing';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
