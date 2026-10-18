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

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_returns_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_returns_no_DELETE` BEFORE DELETE ON `factory_returns` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_returns_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_returns_no_UPDATE` BEFORE UPDATE ON `factory_returns` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_return_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_return_INSERT` BEFORE INSERT ON `factory_returns` FOR EACH ROW
            BEGIN
                IF ( SELECT workflow FROM factory_sales WHERE id = NEW.factory_sale_id ) = 'direct' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sales do not create return stock';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_return_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_return_UPDATE` BEFORE UPDATE ON `factory_returns` FOR EACH ROW
            BEGIN
                IF ( SELECT workflow FROM factory_sales WHERE id = NEW.factory_sale_id ) = 'direct' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sales do not create return stock';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
