<?php

use App\Actions\Finance\PostFinance;
use App\Enums\FinanceKind;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Carbon::setTestNow(Carbon::parse('2026-10-09 03:00:00', 'UTC'));

try {
    $actor = User::findOrFail((int) $argv[1]);
    $data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    $record = app(PostFinance::class)->handle($actor, FinanceKind::Customer, $data);
    echo json_encode(['id' => $record->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['errors' => $error->errors()], JSON_THROW_ON_ERROR);
}
