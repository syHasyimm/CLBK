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

        DB::unprepared('DROP TRIGGER IF EXISTS `audit_logs_no_delete`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `audit_logs_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit bersifat append-only';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `audit_logs_no_update`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `audit_logs_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit bersifat append-only';
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
