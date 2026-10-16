<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Weighing;
use App\Support\Testing\WeighingDateFilterData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SeedDateFilter extends Command
{
    protected $signature = 'clbk:seed-date-filter
        {--staging-origin= : Origin HTTPS resource staging, harus sama dengan APP_URL}
        {--database= : Nama database staging, harus sama dengan koneksi aktif}
        {--manager= : Email manajer uji aktif yang sudah ada}
        {--driver= : Email supir uji aktif yang belum ditugaskan ke mobil lain}';

    protected $description = 'Buat fixture tanggal pada database staging yang dipilih secara eksplisit';

    public function handle(WeighingDateFilterData $fixtures): int
    {
        $origin = rtrim((string) $this->option('staging-origin'), '/');
        $database = (string) $this->option('database');
        if (! str_starts_with($origin, 'https://') || $origin !== rtrim((string) config('app.url'), '/')
            || $database === '' || $database !== DB::connection()->getDatabaseName()
            || ! $this->option('manager') || ! $this->option('driver')) {
            $this->error('Pilih resource staging dan isi --staging-origin, --database, --manager, --driver. Origin/database harus sama dengan konfigurasi aktif. Tidak ada data yang ditulis.');

            return self::FAILURE;
        }
        try {
            $manager = User::where('email', $this->option('manager'))->first();
            $driver = User::where('email', $this->option('driver'))->first();
            if (! $manager || ! $driver) {
                throw new RuntimeException('Buat akun manajer dan supir uji melalui Akun Pengguna terlebih dahulu. Tidak ada akun/password bawaan yang dibuat.');
            }
            $fixtures->seed($manager, $driver);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } catch (Throwable $error) {
            report($error);
            $this->error('Pembuatan fixture gagal. Periksa log staging; jalankan ulang setelah penyebab diperbaiki.');

            return self::FAILURE;
        }
        $this->info('Fixture siap: 1 mobil, 5 tiket sah. Rentang 2026-10-10 sampai 2026-10-12 (WIB) menghasilkan 3 tiket.');
        $this->table(['Tanggal WIB', 'Nomor tiket'], Weighing::whereHas('customer', fn ($query) => $query->where('code', WeighingDateFilterData::CUSTOMER_CODE))
            ->orderBy('business_date')->get()->map(fn ($row) => [$row->business_date, $row->number])->all());

        return self::SUCCESS;
    }
}
