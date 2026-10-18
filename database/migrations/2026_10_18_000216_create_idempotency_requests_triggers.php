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

        DB::unprepared('DROP TRIGGER IF EXISTS `idempotency_requests_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `idempotency_requests_no_DELETE` BEFORE DELETE ON `idempotency_requests` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `idempotency_requests_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `idempotency_requests_no_UPDATE` BEFORE UPDATE ON `idempotency_requests` FOR EACH ROW
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
