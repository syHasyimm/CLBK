<?php

namespace App\Services;

use App\Models\User;
use App\Services\WhatsApp\NotificationOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupService
{
    public function __construct(private BackupSnapshot $snapshot) {}

    public function retention(): array
    {
        return app(BackupRetention::class)->retention();
    }

    public function listing(): array
    {
        $root = $this->root();
        $files = array_values(array_filter(glob($root.'/clbk-*.zip') ?: [], fn ($file) => ! is_link($file) && is_file($file) && preg_match('/\Aclbk-\d{8}T\d{6}Z-[a-f0-9]{12}\.zip\z/', basename($file))));
        usort($files, fn ($a, $b) => (filemtime($b) <=> filemtime($a)) ?: strcmp($b, $a));

        return array_map(fn ($file) => ['id' => basename($file, '.zip'), 'bytes' => filesize($file),
            'created_at' => gmdate('c', filemtime($file))], $files);
    }

    public function path(string $id): string
    {
        if (! preg_match('/\Aclbk-\d{8}T\d{6}Z-[a-f0-9]{12}\z/', $id)) {
            throw new RuntimeException('ID cadangan tidak valid.');
        }
        $path = $this->root().'/'.$id.'.zip';
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Cadangan tidak ditemukan.');
        }

        return $path;
    }

    public function create(?User $actor = null, string $reason = 'manual'): array
    {
        if ($actor) {
            Gate::forUser($actor->fresh())->authorize('manage-settings');
        }

        return $this->locked(fn () => $this->createUnlocked($reason));
    }

    private function createUnlocked(string $reason): array
    {
        $root = $this->root();
        $id = 'clbk-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6));
        $stage = $root.'/.'.$id;
        File::ensureDirectoryExists($stage, 0700);
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (DB::connection()->transactionLevel() !== 0 || DB::connection()->getPdo()->inTransaction()) {
                throw new RuntimeException('Backup harus dimulai di luar transaksi database.');
            }
            if (disk_free_space($root) < max(0, (int) config('backups.minimum_free_bytes'))) {
                throw new RuntimeException('Ruang disk cadangan tidak mencukupi.');
            }
            app(MySqlBackup::class)->export($stage.'/database.jsonl');
            $state = $this->snapshot->inspect($stage.'/database.jsonl');
            $entries = ['database.jsonl' => ['bytes' => filesize($stage.'/database.jsonl'), 'sha256' => hash_file('sha256', $stage.'/database.jsonl')]];
            if ($zip->open($stage.'/archive.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Arsip cadangan tidak dapat dibuat.');
            }
            $opened = true;
            $zip->addFile($stage.'/database.jsonl', 'database.jsonl');
            foreach ($state['proofs'] as $proof) {
                $relative = app(BackupArchive::class)->proofPath($proof['storage_path']);
                $source = $this->privateFile($relative);
                if (! is_file($source) || filesize($source) !== (int) $proof['size_bytes']) {
                    throw new RuntimeException('Bukti yang dirujuk database hilang atau ukurannya berubah.');
                }
                $entry = 'proofs/'.$relative;
                $entries[$entry] = ['bytes' => filesize($source), 'sha256' => hash_file('sha256', $source)];
                $zip->addFile($source, $entry);
            }
            $manifest = ['format' => 2, 'database_driver' => 'mysql', 'id' => $id, 'created_at' => gmdate('c'), 'reason' => $reason,
                'key_fingerprint' => hash('sha256', app(BackupArchive::class)->key()), 'migration_fingerprint' => app(BackupArchive::class)->migrationFingerprint(),
                'entries' => $entries, 'state' => $state];
            $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('manifest.json', $json);
            $zip->addFromString('manifest.hmac', hash_hmac('sha256', $json, app(BackupArchive::class)->key()));
            if (! $zip->close()) {
                throw new RuntimeException('Arsip cadangan gagal diselesaikan.');
            }
            $opened = false;
            $zip = null;
            if (! rename($stage.'/archive.zip', $root.'/'.$id.'.zip')) {
                throw new RuntimeException('Cadangan gagal diterbitkan.');
            }
            @chmod($root.'/'.$id.'.zip', 0600);
            Log::info('Backup completed.', ['backup_id' => $id, 'proof_count' => count($state['proofs'])]);

            return ['id' => $id, 'state' => $state, 'bytes' => filesize($root.'/'.$id.'.zip')];
        } catch (Throwable $error) {
            Log::error('Backup failed.', ['error_type' => $error::class]);
            throw $error;
        } finally {
            if ($opened && $zip instanceof ZipArchive) {
                $zip->close();
            }
            File::deleteDirectory($stage);
        }
    }

    public function verify(string $archive): array
    {
        return $this->locked(function () use ($archive) {
            [$stage, $manifest] = app(BackupArchive::class)->unpack($archive, $this->root(), $this->snapshot);
            try {
                return $manifest;
            } finally {
                File::deleteDirectory($stage);
            }
        });
    }

    public function restore(string $archive, bool $newServer = false): array
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Restore hanya boleh dijalankan dalam maintenance mode.');
        }
        if (config('session.driver') !== 'database') {
            throw new RuntimeException('Restore memerlukan SESSION_DRIVER=database agar seluruh sesi lama dapat dicabut.');
        }

        return $this->locked(function () use ($archive, $newServer) {
            [$stage, $manifest] = app(BackupArchive::class)->unpack($archive, $this->root(), $this->snapshot);
            $safety = null;
            try {
                $expected = collect(File::files(database_path('migrations')))->map(fn ($file) => $file->getBasename('.php'))->sort()->values()->all();
                if ($manifest['state']['migrations'] !== $expected || ($manifest['migration_fingerprint'] ?? '') !== app(BackupArchive::class)->migrationFingerprint()) {
                    throw new RuntimeException('Versi migrasi cadangan berbeda. Gunakan versi aplikasi yang cocok sebelum restore.');
                }
                if (($manifest['database_driver'] ?? '') !== 'mysql') {
                    throw new RuntimeException('Driver cadangan berbeda dari database tujuan. Restore harus memakai engine database sumber yang sama.');
                }
                if ($newServer) {
                    app(MySqlBackup::class)->assertEmpty();
                }
                if (! $newServer) {
                    $safety = $this->createUnlocked('pre-restore');
                }
                // Proof filenames are immutable. Keep extra current files for safety; do not erase business files.
                foreach ($manifest['state']['proofs'] as $proof) {
                    $relative = app(BackupArchive::class)->proofPath($proof['storage_path']);
                    $target = $this->privateFile($relative);
                    File::ensureDirectoryExists(dirname($target), 0700);
                    $temporary = $target.'.restore-'.Str::random(16);
                    if (! copy($stage.'/proofs/'.$relative, $temporary)) {
                        throw new RuntimeException('Bukti cadangan tidak dapat dipulihkan.');
                    }
                    if (is_file($target) && hash_file('sha256', $target) === $manifest['entries']['proofs/'.$relative]['sha256']) {
                        unlink($temporary);
                    } elseif (! rename($temporary, $target)) {
                        unlink($temporary);
                        throw new RuntimeException('Bukti tujuan tidak dapat diganti.');
                    }
                }
                $actual = app(MySqlBackup::class)->restore($stage.'/database.jsonl', $manifest['state']);
                if ($actual !== $manifest['state']) {
                    throw new RuntimeException('Rekonsiliasi setelah restore gagal; aplikasi tetap maintenance.');
                }
                // A restored session/token must not revive credentials from a historical backup.
                DB::transaction(function () {
                    foreach (['sessions', 'password_reset_tokens', 'cache', 'cache_locks'] as $table) {
                        DB::table($table)->delete();
                    }
                    DB::table('users')->update(['remember_token' => null]);
                    app(NotificationOutbox::class)->pauseAfterRestore();
                });
                DB::table('audit_logs')->insert(['actor_id' => null, 'action' => 'backup-restored', 'entity_type' => 'backups',
                    'entity_id' => $manifest['id'], 'before_json' => null,
                    'after_json' => json_encode(['safety_backup_id' => $safety['id'] ?? null], JSON_THROW_ON_ERROR),
                    'reason' => 'Pemulihan oleh operator server dalam maintenance mode.', 'occurred_at' => now()]);
                Log::warning('Backup restored; maintenance remains enabled.', ['backup_id' => $manifest['id'], 'safety_backup_id' => $safety['id'] ?? null]);

                return ['id' => $manifest['id'], 'safety_backup_id' => $safety['id'] ?? null, 'state' => $actual];
            } finally {
                DB::purge();
                File::deleteDirectory($stage);
            }
        });
    }

    public function prune(): array
    {
        return $this->locked(function () {
            $retention = $this->retention();

            return app(BackupRetention::class)->prune($this->listing(), $retention, fn ($id) => $this->path($id));
        });
    }

    private function root(): string
    {
        $root = (string) config('backups.path');
        if ($root === '' || (! str_starts_with($root, '/') && ! preg_match('/\A[A-Za-z]:[\\\\\/]/', $root))) {
            throw new RuntimeException('BACKUP_PATH harus berupa direktori absolut privat.');
        }
        File::ensureDirectoryExists($root, 0700);
        $root = str_replace('\\', '/', realpath($root));
        foreach ([public_path(), config('filesystems.disks.public.root')] as $publicDirectory) {
            $public = realpath($publicDirectory);
            if ($public && str_starts_with(strtolower($root).'/', strtolower(str_replace('\\', '/', $public)).'/')) {
                throw new RuntimeException('Cadangan tidak boleh disimpan di direktori publik.');
            }
        }
        $privateDirectory = (string) config('filesystems.disks.local.root');
        File::ensureDirectoryExists($privateDirectory, 0700);
        $privateDirectory = str_replace('\\', '/', realpath($privateDirectory));
        $comparableRoot = PHP_OS_FAMILY === 'Windows' ? strtolower($root) : $root;
        $comparablePrivate = PHP_OS_FAMILY === 'Windows' ? strtolower($privateDirectory) : $privateDirectory;
        $comparableRoot = rtrim($comparableRoot, '/').'/';
        $comparablePrivate = rtrim($comparablePrivate, '/').'/';
        if (str_starts_with($comparableRoot, $comparablePrivate)
            || str_starts_with($comparablePrivate, $comparableRoot)
            || (PHP_OS_FAMILY !== 'Windows' && stat($root)['dev'] === stat($privateDirectory)['dev'] && stat($root)['ino'] === stat($privateDirectory)['ino'])) {
            throw new RuntimeException('Direktori cadangan harus terpisah dari penyimpanan privat aplikasi.');
        }
        if (app()->isProduction() && config('backups.require_separate_disk', true)) {
            $dataDirectory = $privateDirectory;
            $sameVolume = PHP_OS_FAMILY === 'Windows'
                ? strtolower(substr($root, 0, 2)) === strtolower(substr(realpath($dataDirectory), 0, 2))
                : stat($root)['dev'] === stat($dataDirectory)['dev'];
            if ($sameVolume) {
                throw new RuntimeException('BACKUP_PATH produksi wajib berada di volume berbeda dari data privat aplikasi.');
            }
        }

        return $root;
    }

    private function privateFile(string $relative): string
    {
        $root = (string) config('filesystems.disks.local.root');
        File::ensureDirectoryExists($root, 0700);
        $root = realpath($root);
        $path = $root.'/'.$relative;
        if (is_link(dirname($path)) || is_link($path)) {
            throw new RuntimeException('Symlink pada penyimpanan bukti tidak diizinkan.');
        }

        return $path;
    }

    private function locked(callable $operation): mixed
    {
        $root = $this->root();
        $lock = fopen($root.'/.operations.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            throw new RuntimeException('Operasi backup/restore lain sedang berjalan.');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
