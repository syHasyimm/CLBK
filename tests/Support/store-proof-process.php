<?php

use App\Enums\FinanceKind;
use App\Models\DailyExpense;
use App\Models\User;
use App\Services\ProofStorage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'filesystems.disks.local.root' => $argv[4], 'filesystems.disks.local.serve' => false,
    'proofs.max_per_document' => 5, 'proofs.max_bytes_per_user' => 250 * 1024 * 1024,
    'proofs.max_bytes_total' => (int) $argv[5],
]);

try {
    $actor = User::findOrFail((int) $argv[1]);
    $record = DailyExpense::findOrFail((int) $argv[2]);
    app(ProofStorage::class)->store($actor, $record, 'expense', new UploadedFile($argv[3], 'bukti.pdf', test: true),
        function (User $actor, DailyExpense $record) {
            Gate::forUser($actor)->authorize('create', FinanceKind::Expense->model());
            abort_unless($record->status === 'posted', 403);
        });
    echo json_encode(['stored' => true], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['errors' => $error->errors()], JSON_THROW_ON_ERROR);
}
