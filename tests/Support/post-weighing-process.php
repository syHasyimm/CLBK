<?php

use App\Actions\Weighings\PostWeighing;
use App\Models\User;
use App\Models\Weighing;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $record = app(PostWeighing::class)->handle(User::findOrFail($argv[1]), Weighing::findOrFail($argv[2]),
        ['version' => (int) $argv[3], 'price_schedule_id' => (int) $argv[4], 'idempotency_key' => $argv[5]]);
    echo json_encode(['id' => $record->id, 'status' => $record->status], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['errors' => $error->errors()], JSON_THROW_ON_ERROR);
}
