<?php

namespace Tests\Concerns\FinanceTest;

use App\Actions\Finance\PostFinance;
use App\Actions\Finance\ReverseFinance;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Queries\PaymentBalances;
use App\Services\AuditService;
use App\Services\CashLedger;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

trait FinanceIntegrityCases
{
    public function test_roles_and_bank_method_reference_are_enforced(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->driver)->post(route('customer-payments.store'), $this->payment($ticket))->assertForbidden();
        foreach (['customer-payments.index', 'payroll.index', 'daily-expenses.index', 'cash.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('finance.index', ['kind' => 'receipt']))->assertForbidden();
        $this->get(route('finance-references.index', ['referenceKind' => 'accounts']))->assertForbidden();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket, ['method' => 'bank_transfer']))->assertSessionHasErrors('reference');
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['method' => 'bank_transfer', 'reference' => 'TF-001']))->assertSessionHasNoErrors();
        $record = CustomerPayment::firstOrFail();
        $this->assertSame($this->cash->id, $record->cash_account_id);
        $this->assertSame('bank_transfer', $record->method);
        $this->post(route('finance.reverse', ['kind' => 'customer', 'document' => $record->id]), $this->key(['version' => 1, 'reason' => 'Koreksi', 'return_reference' => 'Kembali']))->assertForbidden();
        $this->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'capital', 'amount_idr' => 1, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Modal']))->assertForbidden();
    }

    public function test_private_proofs_validate_content_size_access_and_are_immutable(): void
    {
        Storage::fake('local');
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        $record = CustomerPayment::firstOrFail();
        $url = route('finance.proofs', ['kind' => 'customer', 'document' => $record->id]);
        $spoofPath = tempnam(sys_get_temp_dir(), 'clbk-proof-');
        try {
            file_put_contents($spoofPath, 'plain text');
            $this->post($url, ['proof' => new UploadedFile($spoofPath, 'nota.pdf', 'application/pdf', test: true)])->assertSessionHasErrors('proof');
        } finally {
            if (is_file($spoofPath)) {
                unlink($spoofPath);
            }
        }
        $this->post($url, ['proof' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('proof');
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertSessionHasNoErrors();
        $file = DB::table('attachments')->where('entity_type', 'customer')->sole();
        Storage::disk('local')->assertExists($file->storage_path);
        $this->get(route('finance-proofs.download', $file->id))->assertDownload('bukti.pdf');
        $this->get(route('sale-proofs.download', $file->id))->assertNotFound();
        $this->actingAs($this->driver)->get(route('finance-proofs.download', $file->id))->assertForbidden();
        try {
            DB::table('attachments')->where('id', $file->id)->delete();
            $this->fail('Posted proof deleted');
        } catch (QueryException) {
            $this->assertDatabaseCount('attachments', 1);
        }
    }

    public function test_late_audit_failure_rolls_back_payment_allocation_cash_and_idempotency(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $keys = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('finance audit failure'));
        });
        try {
            app(PostFinance::class)->handle($this->cashier, FinanceKind::Customer, $this->payment($ticket));
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('finance audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('customer_payment_allocations', 0);
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseCount('idempotency_requests', $keys);
        $this->assertSame(10000000, app(CashLedger::class)->balance($this->cash->id));
    }

    public function test_reversal_preserves_original_and_requires_actual_money_return_reference(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        $record = CustomerPayment::firstOrFail();
        $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => 'customer', 'document' => $record->id]), $this->key(['version' => 1, 'reason' => 'Koreksi']))->assertSessionHasErrors('return_reference');
        $data = $this->key(['version' => 1, 'reason' => 'Koreksi', 'return_reference' => 'Tunai diterima kembali']);
        $this->post(route('finance.reverse', ['kind' => 'customer', 'document' => $record->id]), $data)->assertSessionHasNoErrors();
        $this->post(route('finance.reverse', ['kind' => 'customer', 'document' => $record->id]), $data)->assertSessionHasNoErrors();
        $this->assertSame(0, app(PaymentBalances::class)->customerPaid($ticket->id));
        $this->assertSame(3000000, $record->fresh()->amount_idr);
        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertDatabaseCount('cash_entries', 3);
        $this->assertSame(10000000, app(CashLedger::class)->balance($this->cash->id));
    }

    public function test_two_cashier_processes_cannot_double_pay_a_ticket_or_overdraw_shared_cash(): void
    {
        $first = $this->weigh();
        $second = $this->weigh('1000', Customer::create(['code' => 'K2', 'name' => 'Konsumen Kedua', 'is_active' => true]));
        $this->capital(3000000);
        $otherCashier = User::factory()->cashier()->create();
        foreach ([[$first, $first], [$first, $second]] as $targets) {
            $path = tempnam(sys_get_temp_dir(), 'clbk-payment-');
            unlink($path);
            DB::statement('VACUUM INTO ?', [$path]);
            $pdo = new \PDO('sqlite:'.$path);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo = null;
            $processes = [];
            foreach ($targets as $index => $target) {
                $actor = $index === 0 ? $this->cashier : $otherCashier;
                $payload = $this->payment($target, ['amount_idr' => 3000000]);
                $processes[] = new Process([PHP_BINARY, base_path('tests/Support/post-payment-process.php'), (string) $actor->id, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path]);
            }
            try {
                foreach ($processes as $process) {
                    $process->start();
                }
                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                }
                $outputs = array_map(fn ($process) => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR), $processes);
                $this->assertCount(1, array_filter($outputs, fn ($o) => isset($o['id'])));
                $this->assertCount(1, array_filter($outputs, fn ($o) => isset($o['errors'])));
                $pdo = new \PDO('sqlite:'.$path);
                $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM customer_payments')->fetchColumn());
                $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM customer_payment_allocations')->fetchColumn());
                $this->assertSame(0, (int) $pdo->query("SELECT SUM(CASE direction WHEN 'in' THEN amount_idr ELSE -amount_idr END) FROM cash_entries")->fetchColumn());
                $this->assertSame([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());
                $pdo = null;
            } finally {
                foreach (['', '-wal', '-shm'] as $suffix) {
                    if (is_file($path.$suffix)) {
                        unlink($path.$suffix);
                    }
                }
            }
        }
    }

    public function test_vouchers_allocations_and_cash_ledger_cannot_be_edited_or_deleted(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        foreach ([fn () => DB::table('customer_payments')->update(['amount_idr' => 1]), fn () => DB::table('customer_payment_allocations')->update(['amount_idr' => 1]),
            fn () => DB::table('cash_entries')->update(['amount_idr' => 1]), fn () => DB::table('customer_payments')->delete(),
            fn () => DB::table('customer_payment_allocations')->delete(), fn () => DB::table('cash_entries')->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('Immutable financial record changed');
            } catch (QueryException) {
                $this->assertDatabaseCount('customer_payments', 1);
                $this->assertDatabaseCount('customer_payment_allocations', 1);
                $this->assertDatabaseCount('cash_entries', 2);
            }
        }
    }

    public function test_reversal_audit_failure_restores_status_balance_and_allocations(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $record = app(PostFinance::class)->handle($this->cashier, FinanceKind::Customer, $this->payment($ticket));
        $before = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('reverse finance audit failure'));
        });
        try {
            app(ReverseFinance::class)->handle($this->manager, FinanceKind::Customer, $record, $this->key(['version' => 1, 'reason' => 'Koreksi', 'return_reference' => 'Uang kembali']));
            $this->fail('Expected reversal rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('reverse finance audit failure', $e->getMessage());
        }
        $this->assertSame('posted', $record->fresh()->status);
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertSame(3000000, app(PaymentBalances::class)->customerPaid($ticket->id));
        $this->assertSame(7000000, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('idempotency_requests', $before);
    }
}
