<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => file_put_contents(config('backups.heartbeat_path'), gmdate('c')))
    ->name('scheduler-heartbeat')->everyMinute();

Schedule::command('clbk:backup --scheduled')
    ->dailyAt(config('backups.time', '23:00'))->timezone('Asia/Jakarta')
    ->withoutOverlapping(180)->appendOutputTo(storage_path('logs/backup-scheduler.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
