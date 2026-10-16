<?php

namespace App\Services;

use App\Models\AppSetting;
use ZipArchive;

final class BackupRetention
{
    public function __construct(private BackupArchive $archive) {}

    public function retention(): array
    {
        $settings = AppSetting::query()->whereIn('key', ['backup_keep_daily', 'backup_keep_weekly'])->pluck('value_json', 'key');

        return ['daily' => max(1, (int) ($settings['backup_keep_daily'] ?? config('backups.daily'))),
            'weekly' => max(1, (int) ($settings['backup_keep_weekly'] ?? config('backups.weekly')))];
    }

    public function prune(array $files, array $retention, callable $path): array
    {
        $keep = [];
        $days = [];
        $weeks = [];
        // Manual/safety copies and untrusted archives must never consume scheduled retention slots.
        $files = array_values(array_filter($files, function ($file) use ($path) {
            $zip = new ZipArchive;
            if ($zip->open($path($file['id']), ZipArchive::RDONLY) !== true) {
                return false;
            }
            try {
                $json = $zip->getFromName('manifest.json', 16 * 1024 * 1024);
                $signature = $zip->getFromName('manifest.hmac', 65);

                return is_string($json) && is_string($signature)
                    && hash_equals(hash_hmac('sha256', $json, $this->archive->key()), $signature)
                    && (json_decode($json, true)['reason'] ?? null) === 'scheduled';
            } finally {
                $zip->close();
            }
        }));
        foreach ($files as $file) {
            $timestamp = strtotime($file['created_at']);
            $day = gmdate('Y-m-d', $timestamp);
            $week = gmdate('o-W', $timestamp);
            if ((! isset($days[$day]) && count($days) < $retention['daily'])
                || (! isset($weeks[$week]) && count($weeks) < $retention['weekly'])) {
                $keep[$file['id']] = true;
            }
            if (! isset($days[$day]) && count($days) < $retention['daily']) {
                $days[$day] = true;
            }
            if (! isset($weeks[$week]) && count($weeks) < $retention['weekly']) {
                $weeks[$week] = true;
            }
        }
        $removed = [];
        foreach ($files as $file) {
            if (! isset($keep[$file['id']])) {
                unlink($path($file['id']));
                $removed[] = $file['id'];
            }
        }

        return $removed;
    }
}
