<?php

return [
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'daily' => (int) env('BACKUP_KEEP_DAILY', 7),
    'weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
    'time' => env('BACKUP_TIME', '23:00'),
    'heartbeat_path' => storage_path('framework/scheduler-heartbeat'),
    'minimum_free_bytes' => (int) env('BACKUP_MIN_FREE_BYTES', 1024 * 1024 * 1024),
];
