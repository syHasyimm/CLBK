<?php

namespace Tests\Concerns\FinanceTest;

use App\Queries\PaymentBalances;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait FinanceMigrationsCases
{
    public function test_vehicle_migration_preserves_every_legacy_row_payment_and_master(): void
    {
        $ticket = $this->weigh('1000', sort: '100');
        $this->capital();
        $this->legacyCustomerPayment($this->cashier, $this->cash, [$ticket->id => 1000000]);
        $sale = $this->factorySale();
        $this->actingAs($this->cashier)->post(route('legacy-test.sale-receipts.store'), $this->key(['sale_kind' => 'factory', 'sale_id' => $sale->id,
            'cash_account_id' => $this->cash->id, 'amount_idr' => 1000000, 'expected_remaining_idr' => 3500000,
            'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']))->assertSessionHasNoErrors();
        $migration = require database_path('migrations/2026_10_09_000005_prepare_direct_vehicle_workflow.php');
        $migration->down();
        $snapshot = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $columns = array_column(DB::select('PRAGMA table_info("'.$table->name.'")'), 'name');
            $list = implode(',', array_map(fn ($column) => '"'.$column.'"', $columns));
            $snapshot[$table->name] = [$list, DB::select('SELECT '.$list.' FROM "'.$table->name.'" ORDER BY rowid')];
        }
        // A deleted draft's last issued ID must not be reused by the rebuilt table.
        DB::table('sqlite_sequence')->where('name', 'weighings')->update(['seq' => 123]);
        $migration->up();
        foreach ($snapshot as $table => [$list, $rows]) {
            $this->assertEquals($rows, DB::select('SELECT '.$list.' FROM "'.$table.'" ORDER BY rowid'), $table);
        }
        $this->assertSame(123, DB::table('sqlite_sequence')->where('name', 'weighings')->value('seq'));
        $this->assertSame($this->trip->vehicle_id, $ticket->fresh()->vehicle_id);
        $this->assertSame($this->trip->vehicle_id, $sale->fresh()->vehicle_id);
        $this->assertSame(1000000, app(PaymentBalances::class)->customerPaid($ticket->id));
        $this->assertSame(1000000, app(PaymentBalances::class)->salePaid($sale));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
    }

    public function test_finance_migration_preserves_existing_profit_proof_and_reversal_history(): void
    {
        Storage::fake('local');
        $this->weigh();
        $sale = $this->factorySale();
        $this->post(route('factory-sales.proofs', ['sale' => $sale->id]), ['proof' => UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi nota']))->assertSessionHasNoErrors();
        $profit = DB::table('profit_entries')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $proof = DB::table('attachments')->orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_09_000004_create_finance.php');
        $cashMigration = require database_path('migrations/2026_10_10_000001_mark_automatic_sale_receipts.php');
        $cashMigration->down();
        $migration->down();
        $this->assertSame(1, DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $migration->up();
        $cashMigration->up();
        $this->assertSame($profit, DB::table('profit_entries')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertEquals($proof, DB::table('attachments')->orderBy('id')->get()->toArray());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
    }
}
