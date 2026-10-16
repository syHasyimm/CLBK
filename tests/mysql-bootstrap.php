<?php

require dirname(__DIR__).'/vendor/autoload.php';

// This suite drops tables. Never allow it to select the operational database.
if (getenv('DB_DATABASE') !== 'clbk_mysql_test'
    || ! in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('MySQL tests require a local disposable database named clbk_mysql_test.');
}

$storage = sys_get_temp_dir().'/clbk-mysql-tests-'.bin2hex(random_bytes(6));
foreach (['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    mkdir($storage.'/'.$directory, 0700, true);
}
foreach (['LARAVEL_STORAGE_PATH' => $storage, 'APP_CONFIG_CACHE' => $storage.'/config.php'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
