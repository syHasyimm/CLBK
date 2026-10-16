<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            DB::transaction(function () {
                foreach (['factory_sales', 'sort_sales'] as $table) {
                    $specific = $table === 'factory_sales' ? <<<'SQL'
factory_id INTEGER NOT NULL REFERENCES factories(id) ON DELETE RESTRICT,
factory_price_schedule_id INTEGER REFERENCES factory_price_schedules(id) ON DELETE RESTRICT,
invoice_number TEXT NOT NULL,
initial_load_grams INTEGER NOT NULL CHECK(typeof(initial_load_grams)='integer' AND initial_load_grams BETWEEN 1 AND 1000000000),
sorted_out_grams INTEGER NOT NULL CHECK(typeof(sorted_out_grams)='integer' AND sorted_out_grams BETWEEN 0 AND initial_load_grams),
accepted_grams INTEGER NOT NULL CHECK(typeof(accepted_grams)='integer' AND accepted_grams BETWEEN 1 AND 1000000000),
weight_difference_grams INTEGER NOT NULL CHECK(weight_difference_grams=initial_load_grams-sorted_out_grams-accepted_grams),
variance_approved_by INTEGER REFERENCES users(id) ON DELETE RESTRICT,
variance_approved_version INTEGER, variance_reason TEXT,
SQL : <<<'SQL'
buyer_name TEXT NOT NULL, buyer_contact TEXT,
manual_sold_grams INTEGER NOT NULL CHECK(typeof(manual_sold_grams)='integer' AND manual_sold_grams BETWEEN 1 AND 1000000000),
SQL;
                    DB::unprepared("CREATE TABLE {$table} (
id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE,
trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT, {$specific}
sold_at DATETIME NOT NULL, business_date TEXT NOT NULL,
sell_price_snapshot_idr INTEGER NOT NULL CHECK(typeof(sell_price_snapshot_idr)='integer' AND sell_price_snapshot_idr BETWEEN 1 AND 100000000),
deduction_idr INTEGER NOT NULL CHECK(typeof(deduction_idr)='integer' AND deduction_idr BETWEEN 0 AND 100000000000000),
deduction_reason TEXT, notes TEXT, correction_reason TEXT,
revenue_idr INTEGER NOT NULL CHECK(typeof(revenue_idr)='integer' AND revenue_idr>=0),
purchase_cost_released_idr INTEGER CHECK(purchase_cost_released_idr IS NULL OR (typeof(purchase_cost_released_idr)='integer' AND purchase_cost_released_idr>=0)),
loading_cost_released_idr INTEGER CHECK(loading_cost_released_idr IS NULL OR (typeof(loading_cost_released_idr)='integer' AND loading_cost_released_idr>=0)),
cost_status TEXT NOT NULL DEFAULT 'unavailable' CHECK(cost_status IN ('available','unavailable')),
status TEXT NOT NULL CHECK(status IN ('draft','posted','reversed')),
posted_at DATETIME, posted_by INTEGER REFERENCES users(id) ON DELETE RESTRICT,
reversed_at DATETIME, reversal_business_date TEXT, reversed_by INTEGER REFERENCES users(id) ON DELETE RESTRICT, reversal_reason TEXT,
replaces_sale_id INTEGER UNIQUE REFERENCES {$table}(id) ON DELETE RESTRICT,
version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(version)='integer' AND version>0),
created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT, created_at DATETIME, updated_at DATETIME,
CHECK(deduction_idr=0 OR deduction_reason IS NOT NULL AND length(trim(deduction_reason))>0),
CHECK(cost_status='unavailable' AND purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR cost_status='available' AND purchase_cost_released_idr IS NOT NULL AND loading_cost_released_idr IS NOT NULL),
CHECK(status='draft' OR posted_at IS NOT NULL AND posted_by IS NOT NULL),
CHECK(status!='reversed' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND length(trim(reversal_reason))>0)
);
CREATE INDEX {$table}_trip_status ON {$table}(trip_id,status);
CREATE INDEX {$table}_date_status ON {$table}(business_date,status);
CREATE TRIGGER {$table}_start_draft BEFORE INSERT ON {$table} WHEN NEW.status!='draft' BEGIN SELECT RAISE(ABORT,'Sale must start as draft'); END;
CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} WHEN OLD.status!='draft' BEGIN SELECT RAISE(ABORT,'Posted sale cannot be deleted'); END;");
                }
                DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX one_factory_sale_per_trip ON factory_sales(trip_id) WHERE status IN ('draft','posted');
CREATE UNIQUE INDEX one_active_factory_invoice ON factory_sales(factory_id,invoice_number) WHERE status IN ('draft','posted');
CREATE TABLE factory_returns (
id INTEGER PRIMARY KEY AUTOINCREMENT, factory_sale_id INTEGER NOT NULL UNIQUE REFERENCES factory_sales(id) ON DELETE RESTRICT,
weight_grams INTEGER NOT NULL CHECK(typeof(weight_grams)='integer' AND weight_grams>0),
purchase_cost_carried_idr INTEGER, loading_cost_carried_idr INTEGER, cost_status TEXT NOT NULL CHECK(cost_status IN ('available','unavailable')),
received_at DATETIME NOT NULL, notes TEXT, created_at DATETIME, updated_at DATETIME,
CHECK(cost_status='unavailable' AND purchase_cost_carried_idr IS NULL AND loading_cost_carried_idr IS NULL OR cost_status='available' AND typeof(purchase_cost_carried_idr)='integer' AND purchase_cost_carried_idr>=0 AND typeof(loading_cost_carried_idr)='integer' AND loading_cost_carried_idr>=0)
);
CREATE TABLE sort_sale_items (
id INTEGER PRIMARY KEY AUTOINCREMENT, sort_sale_id INTEGER NOT NULL REFERENCES sort_sales(id) ON DELETE RESTRICT,
lot_id INTEGER NOT NULL REFERENCES sort_lots(id) ON DELETE RESTRICT,
weight_grams INTEGER NOT NULL CHECK(typeof(weight_grams)='integer' AND weight_grams>0),
sell_price_snapshot_idr INTEGER NOT NULL CHECK(typeof(sell_price_snapshot_idr)='integer' AND sell_price_snapshot_idr>0),
gross_revenue_idr INTEGER NOT NULL CHECK(typeof(gross_revenue_idr)='integer' AND gross_revenue_idr>=0),
purchase_cost_released_idr INTEGER, loading_cost_released_idr INTEGER,
CHECK(purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR typeof(purchase_cost_released_idr)='integer' AND purchase_cost_released_idr>=0 AND typeof(loading_cost_released_idr)='integer' AND loading_cost_released_idr>=0),
UNIQUE(sort_sale_id,lot_id)
);
CREATE TABLE sort_lots_new (
id INTEGER PRIMARY KEY AUTOINCREMENT, source_type TEXT NOT NULL CHECK(source_type IN ('INITIAL_SORT','FACTORY_RETURN')),
source_weighing_item_id INTEGER UNIQUE REFERENCES weighing_items(id) ON DELETE RESTRICT,
source_factory_return_id INTEGER UNIQUE REFERENCES factory_returns(id) ON DELETE RESTRICT,
trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT, received_at DATETIME NOT NULL,
initial_grams INTEGER NOT NULL CHECK(typeof(initial_grams)='integer' AND initial_grams BETWEEN 1 AND 1000000000),
initial_purchase_cost_idr INTEGER, initial_loading_cost_idr INTEGER,
cost_status TEXT NOT NULL CHECK(cost_status IN ('available','unavailable')), status TEXT NOT NULL CHECK(status IN ('active','voided')),
created_at DATETIME, updated_at DATETIME,
CHECK(source_type='INITIAL_SORT' AND source_weighing_item_id IS NOT NULL AND source_factory_return_id IS NULL OR source_type='FACTORY_RETURN' AND source_factory_return_id IS NOT NULL AND source_weighing_item_id IS NULL),
CHECK(cost_status='unavailable' AND initial_purchase_cost_idr IS NULL AND initial_loading_cost_idr IS NULL OR cost_status='available' AND typeof(initial_purchase_cost_idr)='integer' AND initial_purchase_cost_idr>=0 AND typeof(initial_loading_cost_idr)='integer' AND initial_loading_cost_idr>=0)
);
INSERT INTO sort_lots_new(id,source_type,source_weighing_item_id,trip_id,received_at,initial_grams,initial_purchase_cost_idr,initial_loading_cost_idr,cost_status,status,created_at,updated_at)
SELECT id,source_type,source_weighing_item_id,trip_id,received_at,initial_grams,initial_purchase_cost_idr,initial_loading_cost_idr,cost_status,status,created_at,updated_at FROM sort_lots;
CREATE TABLE sort_stock_movements_new (
id INTEGER PRIMARY KEY AUTOINCREMENT, lot_id INTEGER NOT NULL REFERENCES sort_lots(id) ON DELETE RESTRICT,
kind TEXT NOT NULL CHECK(kind IN ('receipt','sale','adjustment','reversal')),
quantity_delta_grams INTEGER NOT NULL CHECK(typeof(quantity_delta_grams)='integer' AND quantity_delta_grams!=0),
purchase_cost_delta_idr INTEGER, loading_cost_delta_idr INTEGER,
sort_sale_item_id INTEGER UNIQUE REFERENCES sort_sale_items(id) ON DELETE RESTRICT,
reversal_of_id INTEGER UNIQUE REFERENCES sort_stock_movements(id) ON DELETE RESTRICT,
reason TEXT, occurred_at DATETIME NOT NULL, created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
CHECK(purchase_cost_delta_idr IS NULL AND loading_cost_delta_idr IS NULL OR typeof(purchase_cost_delta_idr)='integer' AND typeof(loading_cost_delta_idr)='integer'),
CHECK(kind='receipt' AND quantity_delta_grams>0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL OR kind='sale' AND quantity_delta_grams<0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NOT NULL OR kind='adjustment' AND quantity_delta_grams<0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length(trim(reason))>0 OR kind='reversal' AND reversal_of_id IS NOT NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length(trim(reason))>0)
);
INSERT INTO sort_stock_movements_new(id,lot_id,kind,quantity_delta_grams,purchase_cost_delta_idr,loading_cost_delta_idr,reversal_of_id,reason,occurred_at,created_by)
SELECT id,lot_id,kind,quantity_delta_grams,purchase_cost_delta_idr,loading_cost_delta_idr,reversal_of_id,reason,occurred_at,created_by FROM sort_stock_movements;
DROP TABLE sort_stock_movements;
DROP TABLE sort_lots;
ALTER TABLE sort_lots_new RENAME TO sort_lots;
ALTER TABLE sort_stock_movements_new RENAME TO sort_stock_movements;
CREATE UNIQUE INDEX one_sort_receipt ON sort_stock_movements(lot_id) WHERE kind='receipt';
CREATE INDEX sort_movements_lot_time ON sort_stock_movements(lot_id,occurred_at);
CREATE INDEX sort_lots_trip_fifo ON sort_lots(trip_id,status,received_at,id);
CREATE TABLE profit_entries (
id INTEGER PRIMARY KEY AUTOINCREMENT,
category TEXT NOT NULL CHECK(category IN ('revenue','purchase_cost','loading_cost','operating_expense','stock_loss')),
amount_delta_idr INTEGER NOT NULL CHECK(typeof(amount_delta_idr)='integer'),
occurred_at DATETIME NOT NULL, business_date TEXT NOT NULL,
trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT,
factory_sale_id INTEGER REFERENCES factory_sales(id) ON DELETE RESTRICT,
sort_sale_id INTEGER REFERENCES sort_sales(id) ON DELETE RESTRICT,
sort_stock_movement_id INTEGER REFERENCES sort_stock_movements(id) ON DELETE RESTRICT,
reversal_of_id INTEGER UNIQUE REFERENCES profit_entries(id) ON DELETE RESTRICT,
created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
CHECK(reversal_of_id IS NULL AND amount_delta_idr>=0 AND ((factory_sale_id IS NOT NULL)+(sort_sale_id IS NOT NULL)+(sort_stock_movement_id IS NOT NULL))=1 OR reversal_of_id IS NOT NULL AND amount_delta_idr<=0 AND factory_sale_id IS NULL AND sort_sale_id IS NULL AND sort_stock_movement_id IS NULL)
);
CREATE UNIQUE INDEX profit_factory_category ON profit_entries(factory_sale_id,category) WHERE reversal_of_id IS NULL;
CREATE UNIQUE INDEX profit_sort_category ON profit_entries(sort_sale_id,category) WHERE reversal_of_id IS NULL;
CREATE UNIQUE INDEX profit_loss_category ON profit_entries(sort_stock_movement_id,category) WHERE reversal_of_id IS NULL;
CREATE INDEX profit_entries_date ON profit_entries(business_date,category);
CREATE TABLE attachments (
id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL CHECK(entity_type IN ('factory','sort')),
entity_id INTEGER NOT NULL, storage_path TEXT NOT NULL UNIQUE, original_name TEXT NOT NULL,
mime_type TEXT NOT NULL, size_bytes INTEGER NOT NULL CHECK(size_bytes BETWEEN 1 AND 10485760),
uploaded_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT, created_at DATETIME NOT NULL
);
CREATE INDEX attachments_entity ON attachments(entity_type,entity_id);
CREATE TRIGGER attachment_source_guard BEFORE INSERT ON attachments
BEGIN SELECT CASE WHEN NEW.entity_type='factory' AND NOT EXISTS(SELECT 1 FROM factory_sales WHERE id=NEW.entity_id) OR NEW.entity_type='sort' AND NOT EXISTS(SELECT 1 FROM sort_sales WHERE id=NEW.entity_id)
THEN RAISE(ABORT,'Missing attachment source') END; END;
CREATE TRIGGER sort_lot_frozen BEFORE UPDATE ON sort_lots WHEN
NEW.id!=OLD.id OR NEW.source_type!=OLD.source_type OR NEW.source_weighing_item_id IS NOT OLD.source_weighing_item_id OR NEW.source_factory_return_id IS NOT OLD.source_factory_return_id OR
NEW.trip_id!=OLD.trip_id OR NEW.received_at!=OLD.received_at OR NEW.initial_grams!=OLD.initial_grams OR NEW.initial_purchase_cost_idr IS NOT OLD.initial_purchase_cost_idr OR NEW.initial_loading_cost_idr IS NOT OLD.initial_loading_cost_idr OR NEW.cost_status!=OLD.cost_status OR
NOT (OLD.status='active' AND NEW.status='voided' AND (SELECT SUM(quantity_delta_grams) FROM sort_stock_movements WHERE lot_id=OLD.id)=0)
BEGIN SELECT RAISE(ABORT,'Source lot is immutable'); END;
CREATE TRIGGER departure_reconciliation BEFORE UPDATE ON trips WHEN OLD.status='loading' AND NEW.status='dispatched'
BEGIN SELECT CASE WHEN NEW.departed_at IS NULL OR NEW.manual_load_grams IS NOT NULL OR EXISTS(SELECT 1 FROM weighings WHERE trip_id=NEW.id AND status='draft') OR
NEW.tbs_purchase_cost_snapshot_idr IS NOT (SELECT SUM(i.purchase_amount_idr) FROM weighing_items i JOIN weighings w ON w.id=i.weighing_id WHERE w.trip_id=NEW.id AND w.status='posted' AND i.fruit_type='TBS') OR
NEW.tbs_loading_cost_snapshot_idr IS NOT (SELECT SUM(i.loading_cost_allocated_idr) FROM weighing_items i JOIN weighings w ON w.id=i.weighing_id WHERE w.trip_id=NEW.id AND w.status='posted' AND i.fruit_type='TBS') OR
(NEW.cost_status='available')!=EXISTS(SELECT 1 FROM weighing_items i JOIN weighings w ON w.id=i.weighing_id WHERE w.trip_id=NEW.id AND w.status='posted' AND i.fruit_type='TBS')
THEN RAISE(ABORT,'Departure costs do not reconcile') END; END;
CREATE TRIGGER dispatched_trip_costs_frozen BEFORE UPDATE ON trips WHEN OLD.departed_at IS NOT NULL AND (
NEW.departed_at IS NOT OLD.departed_at OR NEW.vehicle_id!=OLD.vehicle_id OR NEW.driver_id!=OLD.driver_id OR
NEW.tbs_purchase_cost_snapshot_idr IS NOT OLD.tbs_purchase_cost_snapshot_idr OR NEW.tbs_loading_cost_snapshot_idr IS NOT OLD.tbs_loading_cost_snapshot_idr OR NEW.cost_status!=OLD.cost_status)
BEGIN SELECT RAISE(ABORT,'Departure costs are frozen'); END;
CREATE TRIGGER weighing_after_departure_insert BEFORE INSERT ON weighings WHEN (SELECT status FROM trips WHERE id=NEW.trip_id)!='loading'
BEGIN SELECT RAISE(ABORT,'Trip no longer accepts weighings'); END;
CREATE TRIGGER weighing_after_departure_update BEFORE UPDATE ON weighings WHEN (SELECT departed_at FROM trips WHERE id=OLD.trip_id) IS NOT NULL OR (SELECT status FROM trips WHERE id=NEW.trip_id)!='loading'
BEGIN SELECT RAISE(ABORT,'Dispatched ticket is frozen'); END;
CREATE TRIGGER weighing_after_departure_delete BEFORE DELETE ON weighings WHEN (SELECT departed_at FROM trips WHERE id=OLD.trip_id) IS NOT NULL
BEGIN SELECT RAISE(ABORT,'Dispatched ticket is frozen'); END;
CREATE TRIGGER reconcile_sort_post BEFORE UPDATE ON sort_sales WHEN OLD.status='draft' AND NEW.status='posted'
BEGIN SELECT CASE WHEN
NEW.manual_sold_grams IS NOT (SELECT SUM(weight_grams) FROM sort_sale_items WHERE sort_sale_id=NEW.id) OR
NEW.revenue_idr IS NOT ((SELECT SUM(gross_revenue_idr) FROM sort_sale_items WHERE sort_sale_id=NEW.id)-NEW.deduction_idr) OR
EXISTS(SELECT 1 FROM sort_sale_items i JOIN sort_lots l ON l.id=i.lot_id WHERE i.sort_sale_id=NEW.id AND (l.trip_id!=NEW.trip_id OR i.sell_price_snapshot_idr!=NEW.sell_price_snapshot_idr)) OR
NEW.cost_status='available' AND (EXISTS(SELECT 1 FROM sort_sale_items WHERE sort_sale_id=NEW.id AND purchase_cost_released_idr IS NULL) OR NEW.purchase_cost_released_idr IS NOT (SELECT SUM(purchase_cost_released_idr) FROM sort_sale_items WHERE sort_sale_id=NEW.id) OR NEW.loading_cost_released_idr IS NOT (SELECT SUM(loading_cost_released_idr) FROM sort_sale_items WHERE sort_sale_id=NEW.id)) OR
NEW.cost_status='unavailable' AND NOT EXISTS(SELECT 1 FROM sort_sale_items WHERE sort_sale_id=NEW.id AND purchase_cost_released_idr IS NULL) OR
EXISTS(SELECT 1 FROM sort_sale_items i WHERE i.sort_sale_id=NEW.id AND NOT EXISTS(SELECT 1 FROM sort_stock_movements m WHERE m.sort_sale_item_id=i.id AND m.lot_id=i.lot_id AND m.quantity_delta_grams=-i.weight_grams AND m.purchase_cost_delta_idr IS -i.purchase_cost_released_idr AND m.loading_cost_delta_idr IS -i.loading_cost_released_idr))
THEN RAISE(ABORT,'Sort sale does not reconcile') END; END;
CREATE TRIGGER reconcile_factory_post BEFORE UPDATE ON factory_sales WHEN OLD.status='draft' AND NEW.status='posted'
BEGIN
SELECT CASE WHEN (SELECT status FROM trips WHERE id=NEW.trip_id) NOT IN ('dispatched','correction_pending') OR
NEW.revenue_idr IS NOT ((NEW.accepted_grams*NEW.sell_price_snapshot_idr+500)/1000-NEW.deduction_idr) OR
NEW.cost_status IS NOT (SELECT cost_status FROM trips WHERE id=NEW.trip_id) OR
(NEW.sorted_out_grams>0)!=EXISTS(SELECT 1 FROM factory_returns WHERE factory_sale_id=NEW.id AND weight_grams=NEW.sorted_out_grams) OR
NEW.cost_status='available' AND (NEW.purchase_cost_released_idr IS NOT ((SELECT tbs_purchase_cost_snapshot_idr FROM trips WHERE id=NEW.trip_id)-COALESCE((SELECT purchase_cost_carried_idr FROM factory_returns WHERE factory_sale_id=NEW.id),0)) OR NEW.loading_cost_released_idr IS NOT ((SELECT tbs_loading_cost_snapshot_idr FROM trips WHERE id=NEW.trip_id)-COALESCE((SELECT loading_cost_carried_idr FROM factory_returns WHERE factory_sale_id=NEW.id),0)))
THEN RAISE(ABORT,'Factory sale does not reconcile') END;
SELECT CASE WHEN (NEW.weight_difference_grams<0 OR NEW.weight_difference_grams*10000>NEW.initial_load_grams*200) AND (
NEW.variance_approved_version IS NOT OLD.version OR NEW.variance_reason IS NULL OR length(trim(NEW.variance_reason))=0 OR NOT EXISTS(SELECT 1 FROM users WHERE id=NEW.variance_approved_by AND role='manager' AND is_active=1))
THEN RAISE(ABORT,'Factory variance needs approval') END;
END;
CREATE TRIGGER stock_movement_guard BEFORE INSERT ON sort_stock_movements
BEGIN
SELECT CASE WHEN (SELECT status FROM sort_lots WHERE id=NEW.lot_id)!='active' OR NEW.occurred_at<(SELECT received_at FROM sort_lots WHERE id=NEW.lot_id) OR
NEW.occurred_at<COALESCE((SELECT MAX(occurred_at) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),NEW.occurred_at) OR
((SELECT cost_status FROM sort_lots WHERE id=NEW.lot_id)='available')!=(NEW.purchase_cost_delta_idr IS NOT NULL AND NEW.loading_cost_delta_idr IS NOT NULL) OR
COALESCE((SELECT SUM(quantity_delta_grams) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.quantity_delta_grams NOT BETWEEN 0 AND (SELECT initial_grams FROM sort_lots WHERE id=NEW.lot_id)
THEN RAISE(ABORT,'Invalid stock balance') END;
SELECT CASE WHEN NEW.kind='receipt' AND NOT EXISTS(SELECT 1 FROM sort_lots l WHERE l.id=NEW.lot_id AND NEW.quantity_delta_grams=l.initial_grams AND NEW.purchase_cost_delta_idr IS l.initial_purchase_cost_idr AND NEW.loading_cost_delta_idr IS l.initial_loading_cost_idr) OR
NEW.kind IN ('sale','adjustment') AND (NEW.purchase_cost_delta_idr>0 OR NEW.loading_cost_delta_idr>0)
THEN RAISE(ABORT,'Stock movement does not match source') END;
SELECT CASE WHEN NEW.kind='reversal' AND NOT EXISTS(SELECT 1 FROM sort_stock_movements m WHERE m.id=NEW.reversal_of_id AND m.kind!='reversal' AND m.lot_id=NEW.lot_id AND NEW.quantity_delta_grams=-m.quantity_delta_grams AND NEW.purchase_cost_delta_idr IS -m.purchase_cost_delta_idr AND NEW.loading_cost_delta_idr IS -m.loading_cost_delta_idr)
THEN RAISE(ABORT,'Invalid stock reversal') END;
SELECT CASE WHEN NEW.purchase_cost_delta_idr IS NOT NULL AND (
COALESCE((SELECT SUM(purchase_cost_delta_idr) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.purchase_cost_delta_idr NOT BETWEEN 0 AND (SELECT initial_purchase_cost_idr FROM sort_lots WHERE id=NEW.lot_id) OR
COALESCE((SELECT SUM(loading_cost_delta_idr) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.loading_cost_delta_idr NOT BETWEEN 0 AND (SELECT initial_loading_cost_idr FROM sort_lots WHERE id=NEW.lot_id))
THEN RAISE(ABORT,'Invalid stock cost balance') END;
SELECT CASE WHEN COALESCE((SELECT SUM(quantity_delta_grams) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.quantity_delta_grams=0 AND NEW.purchase_cost_delta_idr IS NOT NULL AND
(COALESCE((SELECT SUM(purchase_cost_delta_idr) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.purchase_cost_delta_idr!=0 OR COALESCE((SELECT SUM(loading_cost_delta_idr) FROM sort_stock_movements WHERE lot_id=NEW.lot_id),0)+NEW.loading_cost_delta_idr!=0)
THEN RAISE(ABORT,'Empty lot must have zero remaining cost') END;
END;
CREATE TRIGGER profit_reversal_guard BEFORE INSERT ON profit_entries WHEN NEW.reversal_of_id IS NOT NULL
BEGIN SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM profit_entries p WHERE p.id=NEW.reversal_of_id AND p.reversal_of_id IS NULL AND NEW.amount_delta_idr=-p.amount_delta_idr AND NEW.category=p.category AND NEW.trip_id=p.trip_id AND NEW.occurred_at>=p.occurred_at)
THEN RAISE(ABORT,'Invalid profit reversal') END; END;
SQL);
                foreach (['factory_sales', 'sort_sales'] as $table) {
                    $columns = array_column(DB::select("PRAGMA table_info({$table})"), 'name');
                    $frozen = array_diff($columns, ['status', 'version', 'updated_at', 'reversed_at', 'reversal_business_date', 'reversed_by', 'reversal_reason']);
                    $condition = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS NOT OLD.{$c}", $frozen));
                    DB::unprepared("CREATE TRIGGER {$table}_freeze BEFORE UPDATE ON {$table} WHEN OLD.status!='draft' AND ({$condition} OR NOT (OLD.status='posted' AND NEW.status='reversed')) BEGIN SELECT RAISE(ABORT,'Posted sale is immutable'); END;");
                    $kind = $table === 'factory_sales' ? 'factory' : 'sort';
                    DB::unprepared("CREATE TRIGGER {$table}_protect_proof BEFORE DELETE ON {$table} WHEN EXISTS(SELECT 1 FROM attachments WHERE entity_type='{$kind}' AND entity_id=OLD.id) BEGIN SELECT RAISE(ABORT,'Sale proof history cannot be deleted'); END;");
                }
                foreach (['sort_stock_movements', 'profit_entries', 'factory_returns'] as $table) {
                    foreach (['UPDATE', 'DELETE'] as $event) {
                        DB::unprepared("CREATE TRIGGER {$table}_no_{$event} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT,'Append-only record'); END;");
                    }
                }
                DB::unprepared("CREATE TRIGGER attachments_no_UPDATE BEFORE UPDATE ON attachments BEGIN SELECT RAISE(ABORT,'Proof metadata is immutable'); END;");
                DB::unprepared("CREATE TRIGGER attachments_no_DELETE BEFORE DELETE ON attachments WHEN OLD.entity_type='factory' AND (SELECT status FROM factory_sales WHERE id=OLD.entity_id)!='draft' OR OLD.entity_type='sort' AND (SELECT status FROM sort_sales WHERE id=OLD.entity_id)!='draft' BEGIN SELECT RAISE(ABORT,'Posted proof cannot be deleted'); END;");
                foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                    $row = $event === 'INSERT' ? 'NEW' : 'OLD';
                    DB::unprepared("CREATE TRIGGER sale_items_freeze_{$event} BEFORE {$event} ON sort_sale_items WHEN (SELECT status FROM sort_sales WHERE id={$row}.sort_sale_id)!='draft' BEGIN SELECT RAISE(ABORT,'Posted sale detail is immutable'); END;");
                }
                DB::unprepared("CREATE TRIGGER sale_items_freeze_move BEFORE UPDATE ON sort_sale_items WHEN NEW.sort_sale_id!=OLD.sort_sale_id AND (SELECT status FROM sort_sales WHERE id=NEW.sort_sale_id)!='draft' BEGIN SELECT RAISE(ABORT,'Cannot move detail to posted sale'); END;");
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Inventory migration failed foreign key validation.');
                }
            });
        });
    }

    public function down(): void
    {
        // Preserve stage-2 source lots and receipt reversals when removing stage-3 history.
        Schema::withoutForeignKeyConstraints(function () {
            DB::transaction(function () {
                foreach (['departure_reconciliation', 'dispatched_trip_costs_frozen', 'weighing_after_departure_insert', 'weighing_after_departure_update', 'weighing_after_departure_delete'] as $trigger) {
                    DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
                }
                $stage2 = file_get_contents(__DIR__.'/2026_10_09_000002_create_prices_and_weighings.php');
                $start = strpos($stage2, 'CREATE TABLE sort_lots (');
                $end = strpos($stage2, 'CREATE TABLE idempotency_requests (');
                $sql = substr($stage2, $start, $end - $start);
                DB::statement("CREATE TEMP TABLE saved_lots AS SELECT id,source_type,source_weighing_item_id,trip_id,received_at,initial_grams,initial_purchase_cost_idr,initial_loading_cost_idr,cost_status,status,created_at,updated_at FROM sort_lots WHERE source_type='INITIAL_SORT'");
                DB::statement("CREATE TEMP TABLE saved_movements AS SELECT id,lot_id,kind,quantity_delta_grams,purchase_cost_delta_idr,loading_cost_delta_idr,reversal_of_id,reason,occurred_at,created_by FROM sort_stock_movements WHERE lot_id IN (SELECT id FROM saved_lots) AND (kind='receipt' OR kind='reversal' AND reversal_of_id IN (SELECT id FROM sort_stock_movements WHERE kind='receipt'))");
                foreach (['attachments', 'profit_entries', 'sort_sale_items', 'sort_stock_movements', 'sort_lots', 'factory_returns', 'sort_sales', 'factory_sales'] as $table) {
                    DB::statement("DROP TABLE {$table}");
                }
                DB::unprepared($sql);
                DB::statement('INSERT INTO sort_lots SELECT * FROM saved_lots');
                DB::statement('INSERT INTO sort_stock_movements SELECT * FROM saved_movements');
                DB::statement('DROP TABLE saved_lots');
                DB::statement('DROP TABLE saved_movements');
                foreach (['UPDATE', 'DELETE'] as $event) {
                    DB::unprepared("CREATE TRIGGER sort_stock_movements_no_{$event} BEFORE {$event} ON sort_stock_movements BEGIN SELECT RAISE(ABORT,'Append-only record'); END;");
                }
            });
        });
    }
};
