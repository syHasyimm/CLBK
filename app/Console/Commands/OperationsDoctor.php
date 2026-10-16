<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Support\Database\MySqlDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OperationsDoctor extends Command
{
    protected $signature = 'clbk:doctor {--json}';

    protected $description = 'Periksa integritas database, umur backup, scheduler, dan ruang disk';

    public function handle(BackupService $backups): int
    {
        $checks = [];
        $errorMessage = null;
        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                $database = app(MySqlDatabase::class);
                $database->assertVersion();
                $checks['mysql_integrity'] = $database->integrity();
                $checks['mysql_business_guards'] = $database->guardsPresent();
                $checks['private_disk_space'] = disk_free_space(config('filesystems.disks.local.root')) >= (int) config('backups.minimum_free_bytes');
            } else {
                $checks['sqlite_integrity'] = DB::selectOne('PRAGMA quick_check')->quick_check === 'ok';
                $checks['sqlite_foreign_keys'] = DB::select('PRAGMA foreign_key_check') === [];
                $checks['data_disk_space'] = disk_free_space(dirname(DB::connection()->getDatabaseName())) >= (int) config('backups.minimum_free_bytes');
            }
            $files = $backups->listing();
            $checks['backup_integrity'] = false;
            $checks['backup_within_24_hours'] = false;
            if ($files !== []) {
                $manifest = $backups->verify($backups->path($files[0]['id']));
                $checks['backup_integrity'] = true;
                $age = time() - strtotime($manifest['created_at']);
                $checks['backup_within_24_hours'] = $age >= 0 && $age <= 86400;
            }
            $heartbeat = config('backups.heartbeat_path');
            $checks['scheduler_within_5_minutes'] = is_file($heartbeat) && time() - filemtime($heartbeat) <= 300;
        } catch (\Throwable $error) {
            $checks['operations_configuration'] = false;
            $errorMessage = $error->getMessage();
        }
        if ($this->option('json')) {
            $this->line(json_encode(['healthy' => ! in_array(false, $checks, true), 'checks' => $checks, 'error' => $errorMessage], JSON_THROW_ON_ERROR));
        } else {
            if ($errorMessage !== null) {
                $this->error($errorMessage);
            }
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? 'OK ' : 'FAIL ').$name);
            }
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
