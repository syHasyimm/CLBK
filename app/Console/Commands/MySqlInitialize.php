<?php

namespace App\Console\Commands;

use App\Support\Database\LegacyMySqlSchema;
use App\Support\Database\MySqlDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class MySqlInitialize extends Command
{
    protected $signature = 'clbk:mysql-initialize';

    protected $description = 'Inisialisasi schema MySQL kosong atau ulangi instalasi kosong yang terhenti';

    public function handle(MySqlDatabase $database): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Inisialisasi ini hanya untuk MySQL 8.4.');

            return self::FAILURE;
        }

        $lock = fopen(storage_path('framework/mysql-initialize.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            $this->error('Inisialisasi MySQL lain sedang berjalan atau folder runtime tidak dapat ditulis.');

            return self::FAILURE;
        }

        try {
            $database->assertVersion();
            if ($this->call('down') !== self::SUCCESS || ! app()->isDownForMaintenance()) {
                $this->error('Maintenance harus aktif sebelum inisialisasi schema.');

                return self::FAILURE;
            }

            $tables = $database->tables();
            $hasHistory = in_array('migrations', $tables, true) && DB::table('migrations')->exists();
            if ($hasHistory) {
                $pending = array_diff(
                    array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))),
                    DB::table('migrations')->pluck('migration')->all(),
                );
                if (in_array('users', $tables, true) && in_array('database_write_locks', $tables, true) && $database->guardsPresent()) {
                    $this->info('Schema sudah terinisialisasi; tidak ada tabel atau data yang diubah. Maintenance tetap aktif.');

                    return self::SUCCESS;
                }
                if ($pending === [] || DB::table('migrations')->where('migration', LegacyMySqlSchema::MIGRATION)->exists()) {
                    $this->error('Riwayat migrasi sudah terisi tetapi schema/trigger belum lengkap. Inisialisasi ditolak; periksa migrasi atau restore tanpa menghapus data.');

                    return self::FAILURE;
                }
            }

            foreach ($tables as $table) {
                if ($table === 'migrations') {
                    continue;
                }
                $query = DB::table($table);
                // The mutex is the only row created by the initial migrations.
                if ($table === 'database_write_locks') {
                    $query->where('id', '<>', 1);
                }
                if ($query->exists()) {
                    $this->error("Tabel {$table} sudah berisi data. Inisialisasi ditolak; gunakan pemeriksaan atau restore yang mempertahankan data.");

                    return self::FAILURE;
                }
            }

            $settings = DB::selectOne('SELECT @@GLOBAL.log_bin AS enabled, @@GLOBAL.log_bin_trust_function_creators AS trusted');
            if ($settings->enabled && ! $settings->trusted) {
                $this->error('Administrator MySQL perlu menjalankan SET PERSIST log_bin_trust_function_creators=ON; lalu ulangi perintah ini.');

                return self::FAILURE;
            }

            // Only an interrupted, unrecorded installation with no business data
            // may be rebuilt. Recorded per-table migrations resume through migrate.
            if (! $hasHistory && array_diff($tables, ['migrations']) !== []) {
                Schema::dropAllTables();
            }

            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            return $this->call('clbk:database-ready');
        } catch (Throwable) {
            $this->error('Inisialisasi schema gagal. Pertahankan maintenance; periksa output migrasi, MySQL 8.4, izin database, dan log privat.');

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
