<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

final class BackupRestore extends Command
{
    protected $signature = 'clbk:restore {archive : File ZIP cadangan} {--verify-only : Verifikasi tanpa mengubah data} {--new-server : Tujuan belum memiliki database} {--force : Operator sudah menghentikan worker dan menyetujui penggantian data}';

    protected $description = 'Verifikasi/pulihkan cadangan; maintenance dan backup kondisi sekarang wajib';

    public function handle(BackupService $backups): int
    {
        try {
            if ($this->option('verify-only')) {
                $manifest = $backups->verify($this->argument('archive'));
                $this->info('Cadangan valid: '.$manifest['id']);
                $this->line(json_encode($manifest['state']['metrics'], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            if (! $this->option('force') && (! $this->input->isInteractive()
                || ! $this->confirm('Worker dihentikan dan database akan diganti. Lanjutkan?', false))) {
                $this->error('Restore dibatalkan. Tidak ada data diganti.');

                return self::FAILURE;
            }
            $started = microtime(true);
            $result = $backups->restore($this->argument('archive'), (bool) $this->option('new-server'));
            $this->info('Pemulihan dan rekonsiliasi selesai: '.$result['id']);
            $this->line('Backup sebelum restore: '.($result['safety_backup_id'] ?? 'database baru'));
            $this->line('Durasi: '.round(microtime(true) - $started, 2).' detik. Maintenance tetap aktif; verifikasi sebelum artisan up.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
