<?php

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Actions\Sales\SaveDirectSale;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ProofStorage;
use App\Support\BusinessClock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

// Builds a NEW isolated acceptance workspace; never opens the operational database.
$root = dirname(__DIR__);
$directory = $root.'/.cache/stage6-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($directory, 0700, true);
touch($directory.'/database.sqlite');
$environment = [
    'APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://127.0.0.1:18066',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $directory.'/database.sqlite', 'DB_URL' => '', 'MAIL_MAILER' => 'array',
    'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'clbk_stage6_'.bin2hex(random_bytes(4)),
    'CACHE_STORE' => 'database', 'CLBK_PRIVATE_STORAGE' => $directory.'/proofs',
    'BACKUP_PATH' => $directory.'/backups', 'BACKUP_MIN_FREE_BYTES' => '0', 'BCRYPT_ROUNDS' => '4',
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== $directory.'/database.sqlite') {
    throw new RuntimeException('Isolated database configuration failed; no migration started.');
}
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$password = 'Stage6Testing123';
$users = [];
foreach (['manager', 'cashier', 'driver'] as $role) {
    $users[$role] = User::create(['name' => 'UAT '.ucfirst($role), 'email' => $role.'@stage6.test',
        'password' => $password, 'role' => $role, 'is_active' => true]);
}
$vehicle = Vehicle::create(['code' => 'UAT-M', 'plate_number' => 'BK UAT 6', 'is_active' => true]);
$users['driver']->update(['assigned_vehicle_id' => $vehicle->id]);
$customer = Customer::create(['code' => 'UAT-K', 'name' => 'Konsumen UAT', 'is_active' => true]);
$worker = LoadingWorker::create(['code' => 'UAT-A', 'name' => 'Anggota UAT', 'is_active' => true]);
$factory = Factory::create(['code' => 'UAT-P', 'name' => 'Pabrik UAT', 'is_active' => true]);
$clock = app(BusinessClock::class);
$price = PriceSchedule::create(['business_date' => $clock->date($clock->now()), 'effective_from' => $clock->now()->subMinutes(10),
    'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $users['manager']->id]);
$factoryPrice = FactoryPriceSchedule::create(['factory_id' => $factory->id, 'effective_from' => $clock->now()->subMinutes(10),
    'tbs_sell_price_idr' => 3500, 'published_by' => $users['manager']->id]);
$key = fn ($data) => $data + ['idempotency_key' => (string) Str::uuid()];
app(ManualCash::class)->post($users['manager'], $key(['kind' => 'opening', 'amount_idr' => 5000000,
    'occurred_at' => $clock->local($clock->now()), 'reason' => 'Modal UAT']));
$ticket = app(SaveDirectWeighing::class)->handle($users['driver'], $key([
    'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1,
    'tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id,
]));
$sales = [];
foreach (['factory', 'sort'] as $kind) {
    $sales[$kind] = app(SaveDirectSale::class)->handle($users['driver'], $kind, $key([
        'vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1,
    ] + ($kind === 'factory' ? ['factory_id' => $factory->id, 'factory_price_schedule_id' => $factoryPrice->id,
        'accepted_kg' => '900', 'deduction_idr' => 0] : ['sold_kg' => '100', 'sell_price_snapshot_idr' => 2000, 'buyer_name' => 'Pembeli UAT'])));
    app(PostFinance::class)->handle($users['cashier'], FinanceKind::Receipt, $key([
        'sale_kind' => $kind, 'sale_id' => $sales[$kind]->id, 'amount_idr' => $sales[$kind]->revenue_idr,
        'expected_remaining_idr' => $sales[$kind]->revenue_idr, 'method' => 'cash', 'occurred_at' => $clock->local($clock->now()),
    ]));
}
$expense = app(PostFinance::class)->handle($users['cashier'], FinanceKind::Expense, $key([
    'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'amount_idr' => 50000,
    'description' => 'BBM UAT', 'method' => 'cash', 'occurred_at' => $clock->local($clock->now()),
]));
$file = UploadedFile::fake()->createWithContent('bukti-uat.pdf', "%PDF-1.4\nUAT proof\n%%EOF");
app(ProofStorage::class)->store($users['cashier'], $expense, 'expense', $file,
    fn ($actor, $record) => Gate::forUser($actor)->authorize('create', $record::class));
$context = ['environment' => $environment, 'users' => array_map(fn ($user) => $user->email, $users),
    'password' => $password, 'business_date' => $clock->date($clock->now()), 'expense_id' => $expense->id,
    'ticket_id' => $ticket->id, 'proof_id' => DB::table('attachments')->value('id')];
file_put_contents($directory.'/context.json', json_encode($context, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo $directory.'/context.json'.PHP_EOL;
