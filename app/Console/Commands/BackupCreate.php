<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

final class BackupCreate extends Command
{
    protected $signature = 'clbk:backup {--scheduled : Terapkan retensi cadangan terjadwal} {--reason=manual}';

    protected $description = 'Cadangkan snapshot database konsisten dan seluruh bukti privat';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create(reason: $this->option('scheduled') ? 'scheduled' : (string) $this->option('reason'));
            $this->info('Cadangan selesai: '.$result['id']);
            $this->line('SHA256: '.hash_file('sha256', $backups->path($result['id'])));
            if ($this->option('scheduled')) {
                $backups->prune();
            }

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
