<?php

// Setup lokal berulang tidak mengganti kunci aplikasi atau database yang sudah ada.
$root = dirname(__DIR__);
$environmentPath = $root.'/.env';
if (! is_file($environmentPath)) {
    copy($root.'/.env.example', $environmentPath);
}

$contents = file_get_contents($environmentPath);
if (preg_match('/^APP_KEY=\s*$/m', $contents)) {
    $contents = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $contents);
    file_put_contents($environmentPath, $contents);
}

$databaseFile = $root.'/database/database.sqlite';
if (! is_file($databaseFile)) {
    touch($databaseFile);
}

echo "Konfigurasi lokal siap.\n";
