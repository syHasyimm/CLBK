<?php

use Database\Seeders\WeighingDateFilterSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Creates a new disposable workspace. Never migrates or seeds the operational database.
$root = dirname(__DIR__);
$directory = $root.'/.cache/date-filter-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($directory.'/storage/framework/sessions', 0700, true);
mkdir($directory.'/storage/framework/views', 0700, true);
mkdir($directory.'/storage/framework/cache/data', 0700, true);
mkdir($directory.'/storage/logs', 0700, true);
touch($directory.'/database.sqlite');
$environment = [
    'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:18067',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_CONFIG_CACHE' => str_replace($root.'/', '', $directory).'/config.php',
    'APP_ROUTES_CACHE' => str_replace($root.'/', '', $directory).'/routes.php',
    'APP_PACKAGES_CACHE' => str_replace($root.'/', '', $directory).'/packages.php',
    'APP_SERVICES_CACHE' => str_replace($root.'/', '', $directory).'/services.php',
    'APP_MAINTENANCE_DRIVER' => 'file', 'LARAVEL_STORAGE_PATH' => $directory.'/storage',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite', 'DB_URL' => '',
    'SESSION_DRIVER' => 'database', 'SESSION_CONNECTION' => '', 'SESSION_COOKIE' => 'clbk_date_filter_test',
    'SESSION_ENCRYPT' => 'true', 'SESSION_DOMAIN' => '', 'SESSION_PATH' => '/',
    'SESSION_SECURE_COOKIE' => 'false', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax',
    'CACHE_STORE' => 'database', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'CLBK_PRIVATE_STORAGE' => $directory.'/proofs', 'BACKUP_PATH' => $directory.'/backups',
    'XDG_CONFIG_HOME' => $directory.'/config', 'BCRYPT_ROUNDS' => '4',
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== $directory.'/database.sqlite' || ! app()->environment('local')) {
    throw new RuntimeException('Isolated database configuration failed; no migration or seeding started.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0
    || Artisan::call('db:seed', ['--class' => WeighingDateFilterSeeder::class, '--force' => true]) !== 0) {
    throw new RuntimeException('Fixture preparation failed. Inspect the isolated workspace logs.');
}
file_put_contents($directory.'/context.json', json_encode([
    'environment' => $environment,
    'users' => ['manager' => 'manager@clbk.test', 'driver' => 'supir@clbk.test', 'cashier' => 'kasir@clbk.test'],
    'password' => 'ClbkToke12345!', 'from' => '2026-10-10', 'to' => '2026-10-12',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo $directory.'/context.json'.PHP_EOL;
