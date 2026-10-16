<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Domain\Inventory\Stock;
use App\Models\SortLot;
use Illuminate\Support\Facades\DB;

trait InventoryMigrationsCases
{
    public function test_inventory_schema_upgrade_preserves_stage_two_ids_costs_reversals_and_foreign_keys(): void
    {
        $ticket = $this->weigh('700', '100');
        $lotBefore = SortLot::firstOrFail()->toArray();
        $movementBefore = DB::table('sort_stock_movements')->first();
        $migration = require database_path('migrations/2026_10_09_000003_create_sales_and_inventory.php');
        $financeMigration = require database_path('migrations/2026_10_09_000004_create_finance.php');
        $vehicleMigration = require database_path('migrations/2026_10_09_000005_prepare_direct_vehicle_workflow.php');
        $cashMigration = require database_path('migrations/2026_10_10_000001_mark_automatic_sale_receipts.php');
        $cashMigration->down();
        $vehicleMigration->down();
        $financeMigration->down();
        $migration->down();
        $this->assertSame(1, DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $migration->up();
        $financeMigration->up();
        $vehicleMigration->up();
        $cashMigration->up();
        $lot = SortLot::firstOrFail();
        $this->assertSame($lotBefore, $lot->toArray());
        $this->assertEquals($movementBefore, DB::table('sort_stock_movements')->first());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
        $cashMigration->down();
        $vehicleMigration->down();
        $financeMigration->down();
        $migration->down();
        $migration->up();
        $financeMigration->up();
        $vehicleMigration->up();
        $cashMigration->up();
        $this->assertSame('voided', SortLot::firstOrFail()->status);
        $this->assertSame(0, app(Stock::class)->balance(SortLot::firstOrFail())['grams']);
        $this->assertDatabaseCount('sort_stock_movements', 2);
    }
}
