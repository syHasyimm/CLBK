<?php

namespace App\Console\Commands;

use App\Support\Database\MySqlDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

final class DatabaseReady extends Command
{
    protected $signature = 'clbk:database-ready {--wait=0 : Detik menunggu koneksi MySQL} {--serve-maintenance : Izinkan server berjalan dalam maintenance saat schema belum lengkap}';

    protected $description = 'Periksa MySQL dan tutup instalasi yang belum diinisialisasi';

    public function handle(MySqlDatabase $database): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Runtime produksi memerlukan DB_CONNECTION=mysql.');

            return self::FAILURE;
        }
        $deadline = microtime(true) + max(0, min(120, (int) $this->option('wait')));
        do {
            try {
                DB::connection()->getPdo();
                break;
            } catch (PDOException) {
                DB::purge();
                if (microtime(true) >= $deadline) {
                    $this->error('MySQL belum dapat dihubungi. Periksa status resource, network, dan runtime DB_* di Coolify.');

                    return self::FAILURE;
                }
                sleep(1);
            }
        } while (true);

        try {
            $database->assertVersion();
            $tables = $database->tables();
            if ($tables === [] || $tables === ['migrations']) {
                Artisan::call('down');
                $this->info('MySQL kosong: aplikasi tetap maintenance. Jalankan migrate, seed, dan bootstrap melalui Terminal Coolify.');

                return self::SUCCESS;
            }
            if (! in_array('users', $tables, true) || ! in_array('database_write_locks', $tables, true) || ! $database->guardsPresent()) {
                Artisan::call('down');
                $this->error('Schema/trigger MySQL belum lengkap. Aplikasi tetap maintenance; periksa hasil migrasi atau restore.');

                return $this->option('serve-maintenance') && app()->isDownForMaintenance()
                    ? self::SUCCESS : self::FAILURE;
            }
            if (! DB::table('users')->exists()) {
                Artisan::call('down');
                $this->info('Akun awal belum dibuat; selesaikan bootstrap sebelum artisan up.');
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Pemeriksaan schema MySQL gagal. Gunakan MySQL 8.4 dan periksa migrasi/izin database.');

            return self::FAILURE;
        }
    }
}
