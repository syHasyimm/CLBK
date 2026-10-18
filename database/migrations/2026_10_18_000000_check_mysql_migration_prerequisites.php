<?php

use App\Support\Database\LegacyMySqlSchema;
use App\Support\Database\MySqlDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $database = app(MySqlDatabase::class);
        $database->assertVersion();
        $settings = DB::selectOne('SELECT @@GLOBAL.log_bin AS enabled, @@GLOBAL.log_bin_trust_function_creators AS trusted');
        if ($settings->enabled && ! $settings->trusted) {
            throw new RuntimeException('Administrator MySQL perlu menjalankan SET PERSIST log_bin_trust_function_creators=ON; sebelum migrate.');
        }

        if (app(LegacyMySqlSchema::class)->adoptExistingSchema()) {
            return;
        }
        if (array_diff($database->tables(), ['migrations']) !== []) {
            throw new RuntimeException('Database sudah memiliki tabel tanpa riwayat migrasi yang sesuai. Gunakan pemeriksaan atau restore; migrate tidak akan menghapus data.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
