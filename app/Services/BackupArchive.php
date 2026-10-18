<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupArchive
{
    public function unpack(string $archive, string $root, BackupSnapshot $snapshot): array
    {
        $stage = $root.'/.restore-'.Str::uuid();
        File::ensureDirectoryExists($stage, 0700);
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Arsip cadangan tidak valid.');
            }
            $opened = true;
            $info = $zip->statName('manifest.json');
            if (! $info || $info['size'] > 16 * 1024 * 1024) {
                throw new RuntimeException('Manifest cadangan tidak valid.');
            }
            $json = $zip->getFromName('manifest.json');
            $signature = $zip->getFromName('manifest.hmac', 65);
            if (! is_string($json) || ! is_string($signature) || ! hash_equals(hash_hmac('sha256', $json, $this->key()), $signature)) {
                throw new RuntimeException('Tanda tangan cadangan tidak cocok. Periksa APP_KEY dan keaslian arsip.');
            }
            $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 2 || ($manifest['database_driver'] ?? null) !== 'mysql' || ! isset($manifest['entries']['database.jsonl'], $manifest['state'])
                || $zip->numFiles !== count($manifest['entries']) + 2) {
                throw new RuntimeException('Struktur cadangan tidak dikenali.');
            }
            foreach ($manifest['entries'] as $entry => $metadata) {
                if ($entry !== 'database.jsonl') {
                    if (! str_starts_with($entry, 'proofs/')) {
                        throw new RuntimeException('Entri cadangan tidak diizinkan.');
                    }
                    $this->proofPath(substr($entry, 7));
                }
                $target = $stage.'/'.$entry;
                File::ensureDirectoryExists(dirname($target), 0700);
                $input = $zip->getStream($entry);
                $output = fopen($target, 'xb');
                if (! $input || ! $output) {
                    throw new RuntimeException('Entri cadangan tidak dapat dibaca.');
                }
                try {
                    $bytes = stream_copy_to_stream($input, $output, (int) $metadata['bytes'] + 1);
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if ($bytes !== $metadata['bytes'] || ! hash_equals($metadata['sha256'], hash_file('sha256', $target))) {
                    throw new RuntimeException('Checksum atau ukuran isi cadangan tidak cocok.');
                }
            }
            if ($snapshot->inspect($stage.'/database.jsonl') !== $manifest['state']) {
                throw new RuntimeException('Snapshot cadangan tidak cocok dengan manifest.');
            }
            foreach ($manifest['state']['proofs'] as $proof) {
                $entry = 'proofs/'.$this->proofPath($proof['storage_path']);
                if (! isset($manifest['entries'][$entry]) || $manifest['entries'][$entry]['bytes'] !== (int) $proof['size_bytes']) {
                    throw new RuntimeException('Bukti pada snapshot tidak lengkap dalam arsip.');
                }
            }

            return [$stage, $manifest];
        } catch (Throwable $error) {
            File::deleteDirectory($stage);
            throw $error;
        } finally {
            if ($opened) {
                $zip->close();
            }
        }
    }

    public function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY diperlukan untuk menandatangani dan memverifikasi cadangan.');
        }

        return $key;
    }

    public function migrationFingerprint(): string
    {
        $files = collect(File::files(database_path('migrations')))->sortBy(fn ($file) => $file->getFilename());

        $fingerprints = $files->map(fn ($file) => $file->getFilename().':'.hash_file('sha256', $file->getPathname()))->all();

        return hash('sha256', implode("\n", $fingerprints));
    }

    public function proofPath(string $path): string
    {
        if (! preg_match('/\A(?:sale-proofs|finance-proofs)\/[A-Za-z0-9._-]+\z/', $path) || str_contains($path, '..')) {
            throw new RuntimeException('Lokasi bukti tidak valid.');
        }

        return $path;
    }
}
