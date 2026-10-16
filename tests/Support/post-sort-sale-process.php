<?php

use App\Actions\Sales\PostSale;
use App\Models\SortSale;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    $sale = app(PostSale::class)->handle(User::findOrFail($argv[1]), SortSale::findOrFail($argv[2]),
        ['version' => (int) $argv[3], 'preview_hash' => $argv[4], 'idempotency_key' => $argv[5]]);
    echo json_encode(['id' => $sale->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['errors' => $error->errors()], JSON_THROW_ON_ERROR);
}
