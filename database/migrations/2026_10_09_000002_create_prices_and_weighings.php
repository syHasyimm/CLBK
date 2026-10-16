<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE document_sequences (
    name TEXT PRIMARY KEY, next_value INTEGER NOT NULL CHECK(typeof(next_value) = 'integer' AND next_value > 0)
);
CREATE TABLE price_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT, business_date TEXT NOT NULL,
    effective_from DATETIME NOT NULL UNIQUE,
    tbs_buy_price_idr INTEGER NOT NULL CHECK(typeof(tbs_buy_price_idr) = 'integer' AND tbs_buy_price_idr BETWEEN 1 AND 100000000),
    sort_buy_price_idr INTEGER NOT NULL CHECK(typeof(sort_buy_price_idr) = 'integer' AND sort_buy_price_idr BETWEEN 1 AND 100000000),
    loading_rate_idr INTEGER NOT NULL CHECK(typeof(loading_rate_idr) = 'integer' AND loading_rate_idr BETWEEN 0 AND 100000000),
    notes TEXT, published_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at DATETIME, updated_at DATETIME
);
CREATE INDEX prices_date_effective ON price_schedules(business_date, effective_from);
CREATE TABLE factory_price_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT, factory_id INTEGER NOT NULL REFERENCES factories(id) ON DELETE RESTRICT,
    effective_from DATETIME NOT NULL,
    tbs_sell_price_idr INTEGER NOT NULL CHECK(typeof(tbs_sell_price_idr) = 'integer' AND tbs_sell_price_idr BETWEEN 1 AND 100000000),
    published_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at DATETIME, updated_at DATETIME, UNIQUE(factory_id, effective_from)
);
CREATE TABLE trips (
    id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE,
    vehicle_id INTEGER NOT NULL REFERENCES vehicles(id) ON DELETE RESTRICT,
    driver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    planned_factory_id INTEGER REFERENCES factories(id) ON DELETE RESTRICT,
    business_date TEXT NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('loading','dispatched','factory_recorded','closed','cancelled','correction_pending')),
    departed_at DATETIME, closed_at DATETIME,
    manual_load_grams INTEGER CHECK(manual_load_grams IS NULL OR (typeof(manual_load_grams) = 'integer' AND manual_load_grams BETWEEN 1 AND 1000000000)),
    tbs_purchase_cost_snapshot_idr INTEGER CHECK(tbs_purchase_cost_snapshot_idr IS NULL OR (typeof(tbs_purchase_cost_snapshot_idr) = 'integer' AND tbs_purchase_cost_snapshot_idr >= 0)),
    tbs_loading_cost_snapshot_idr INTEGER CHECK(tbs_loading_cost_snapshot_idr IS NULL OR (typeof(tbs_loading_cost_snapshot_idr) = 'integer' AND tbs_loading_cost_snapshot_idr >= 0)),
    cost_status TEXT NOT NULL DEFAULT 'unavailable' CHECK(cost_status IN ('available','unavailable')),
    version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(version) = 'integer' AND version > 0),
    created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at DATETIME, updated_at DATETIME
);
CREATE UNIQUE INDEX one_active_trip_per_vehicle ON trips(vehicle_id) WHERE status IN ('loading','dispatched');
CREATE INDEX trips_vehicle_status_date ON trips(vehicle_id,status,business_date);
CREATE INDEX trips_driver_status ON trips(driver_id,status);
CREATE TABLE weighings (
    id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
    trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT,
    weighed_at DATETIME NOT NULL, business_date TEXT NOT NULL, backdate_reason TEXT,
    price_schedule_id INTEGER REFERENCES price_schedules(id) ON DELETE RESTRICT,
    loading_rate_snapshot_idr INTEGER CHECK(loading_rate_snapshot_idr IS NULL OR (typeof(loading_rate_snapshot_idr) = 'integer' AND loading_rate_snapshot_idr >= 0)),
    loading_basis_grams INTEGER CHECK(loading_basis_grams IS NULL OR (typeof(loading_basis_grams) = 'integer' AND loading_basis_grams > 0)),
    total_purchase_idr INTEGER CHECK(total_purchase_idr IS NULL OR (typeof(total_purchase_idr) = 'integer' AND total_purchase_idr >= 0)),
    total_loading_wage_idr INTEGER CHECK(total_loading_wage_idr IS NULL OR (typeof(total_loading_wage_idr) = 'integer' AND total_loading_wage_idr >= 0)),
    status TEXT NOT NULL CHECK(status IN ('draft','posted','voided')),
    posted_at DATETIME, posted_by INTEGER REFERENCES users(id) ON DELETE RESTRICT,
    voided_at DATETIME, voided_by INTEGER REFERENCES users(id) ON DELETE RESTRICT, void_reason TEXT,
    replaces_weighing_id INTEGER REFERENCES weighings(id) ON DELETE RESTRICT,
    version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(version) = 'integer' AND version > 0),
    created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at DATETIME, updated_at DATETIME,
    CHECK(status = 'draft' OR (price_schedule_id IS NOT NULL AND loading_rate_snapshot_idr IS NOT NULL AND loading_basis_grams IS NOT NULL AND total_purchase_idr IS NOT NULL AND total_loading_wage_idr IS NOT NULL AND posted_at IS NOT NULL AND posted_by IS NOT NULL)),
    CHECK(status != 'voided' OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL AND length(trim(void_reason)) > 0))
);
CREATE INDEX weighings_customer_status_date ON weighings(customer_id,status,weighed_at);
CREATE INDEX weighings_trip_status ON weighings(trip_id,status);
CREATE TABLE weighing_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, weighing_id INTEGER NOT NULL REFERENCES weighings(id) ON DELETE RESTRICT,
    fruit_type TEXT NOT NULL CHECK(fruit_type IN ('TBS','SORT')),
    weight_grams INTEGER NOT NULL CHECK(typeof(weight_grams) = 'integer' AND weight_grams BETWEEN 1 AND 1000000000),
    buy_price_snapshot_idr INTEGER CHECK(buy_price_snapshot_idr IS NULL OR (typeof(buy_price_snapshot_idr) = 'integer' AND buy_price_snapshot_idr > 0)),
    purchase_amount_idr INTEGER CHECK(purchase_amount_idr IS NULL OR (typeof(purchase_amount_idr) = 'integer' AND purchase_amount_idr >= 0)),
    loading_basis_grams INTEGER CHECK(loading_basis_grams IS NULL OR (typeof(loading_basis_grams) = 'integer' AND loading_basis_grams > 0)),
    loading_cost_allocated_idr INTEGER CHECK(loading_cost_allocated_idr IS NULL OR (typeof(loading_cost_allocated_idr) = 'integer' AND loading_cost_allocated_idr >= 0)),
    created_at DATETIME, updated_at DATETIME, UNIQUE(weighing_id,fruit_type)
);
CREATE TABLE weighing_loaders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, weighing_id INTEGER NOT NULL REFERENCES weighings(id) ON DELETE RESTRICT,
    worker_id INTEGER NOT NULL REFERENCES loading_workers(id) ON DELETE RESTRICT,
    member_count_snapshot INTEGER CHECK(member_count_snapshot IS NULL OR (typeof(member_count_snapshot) = 'integer' AND member_count_snapshot > 0)),
    share_amount_idr INTEGER CHECK(share_amount_idr IS NULL OR (typeof(share_amount_idr) = 'integer' AND share_amount_idr >= 0)),
    allocation_order INTEGER CHECK(allocation_order IS NULL OR (typeof(allocation_order) = 'integer' AND allocation_order > 0)),
    created_at DATETIME, updated_at DATETIME, UNIQUE(weighing_id,worker_id)
);
CREATE INDEX loaders_worker_weighing ON weighing_loaders(worker_id,weighing_id);
CREATE TABLE sort_lots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_type TEXT NOT NULL CHECK(source_type = 'INITIAL_SORT'),
    source_weighing_item_id INTEGER NOT NULL UNIQUE REFERENCES weighing_items(id) ON DELETE RESTRICT,
    trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT,
    received_at DATETIME NOT NULL,
    initial_grams INTEGER NOT NULL CHECK(typeof(initial_grams) = 'integer' AND initial_grams > 0),
    initial_purchase_cost_idr INTEGER NOT NULL CHECK(typeof(initial_purchase_cost_idr) = 'integer' AND initial_purchase_cost_idr >= 0),
    initial_loading_cost_idr INTEGER NOT NULL CHECK(typeof(initial_loading_cost_idr) = 'integer' AND initial_loading_cost_idr >= 0),
    cost_status TEXT NOT NULL CHECK(cost_status = 'available'),
    status TEXT NOT NULL CHECK(status IN ('active','voided')),
    created_at DATETIME, updated_at DATETIME
);
CREATE TABLE sort_stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT, lot_id INTEGER NOT NULL REFERENCES sort_lots(id) ON DELETE RESTRICT,
    kind TEXT NOT NULL CHECK(kind IN ('receipt','reversal')),
    quantity_delta_grams INTEGER NOT NULL CHECK(typeof(quantity_delta_grams) = 'integer' AND quantity_delta_grams != 0),
    purchase_cost_delta_idr INTEGER NOT NULL CHECK(typeof(purchase_cost_delta_idr) = 'integer'),
    loading_cost_delta_idr INTEGER NOT NULL CHECK(typeof(loading_cost_delta_idr) = 'integer'),
    reversal_of_id INTEGER UNIQUE REFERENCES sort_stock_movements(id) ON DELETE RESTRICT,
    reason TEXT, occurred_at DATETIME NOT NULL, created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    CHECK((kind = 'receipt' AND reversal_of_id IS NULL AND quantity_delta_grams > 0 AND purchase_cost_delta_idr >= 0 AND loading_cost_delta_idr >= 0) OR
          (kind = 'reversal' AND reversal_of_id IS NOT NULL AND quantity_delta_grams < 0 AND purchase_cost_delta_idr <= 0 AND loading_cost_delta_idr <= 0 AND reason IS NOT NULL AND length(trim(reason)) > 0))
);
CREATE UNIQUE INDEX one_sort_receipt ON sort_stock_movements(lot_id) WHERE kind = 'receipt';
CREATE INDEX sort_movements_lot_time ON sort_stock_movements(lot_id,occurred_at);
CREATE TABLE idempotency_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    operation TEXT NOT NULL, key TEXT NOT NULL, request_hash TEXT NOT NULL,
    response_reference INTEGER NOT NULL, status TEXT NOT NULL CHECK(status = 'completed'),
    created_at DATETIME NOT NULL, UNIQUE(actor_id,operation,key)
);
CREATE TRIGGER freeze_posted_weighing BEFORE UPDATE ON weighings
WHEN OLD.status != 'draft' AND (
    NEW.customer_id != OLD.customer_id OR NEW.trip_id != OLD.trip_id OR NEW.number != OLD.number OR
    NEW.weighed_at != OLD.weighed_at OR NEW.business_date != OLD.business_date OR
    NEW.price_schedule_id IS NOT OLD.price_schedule_id OR NEW.loading_rate_snapshot_idr IS NOT OLD.loading_rate_snapshot_idr OR
    NEW.loading_basis_grams IS NOT OLD.loading_basis_grams OR NEW.total_purchase_idr IS NOT OLD.total_purchase_idr OR
    NEW.total_loading_wage_idr IS NOT OLD.total_loading_wage_idr OR NEW.posted_at IS NOT OLD.posted_at OR
    NEW.posted_by IS NOT OLD.posted_by OR NEW.created_by != OLD.created_by OR
    NEW.replaces_weighing_id IS NOT OLD.replaces_weighing_id OR NEW.backdate_reason IS NOT OLD.backdate_reason OR
    NOT (OLD.status = 'posted' AND NEW.status = 'voided')
)
BEGIN SELECT RAISE(ABORT, 'Posted weighing is immutable'); END;
CREATE TRIGGER no_delete_posted_weighing BEFORE DELETE ON weighings WHEN OLD.status != 'draft'
BEGIN SELECT RAISE(ABORT, 'Posted weighing cannot be deleted'); END;
CREATE TRIGGER weighings_must_start_as_draft BEFORE INSERT ON weighings WHEN NEW.status != 'draft'
BEGIN SELECT RAISE(ABORT, 'Weighing must start as draft'); END;
CREATE TRIGGER reconcile_weighing_on_post BEFORE UPDATE ON weighings WHEN OLD.status = 'draft' AND NEW.status = 'posted'
BEGIN
    SELECT CASE WHEN
        (SELECT COUNT(*) FROM weighing_items WHERE weighing_id = NEW.id) = 0 OR
        (SELECT COUNT(*) FROM weighing_loaders WHERE weighing_id = NEW.id) = 0 OR
        EXISTS(SELECT 1 FROM weighing_items WHERE weighing_id = NEW.id AND (buy_price_snapshot_idr IS NULL OR purchase_amount_idr IS NULL OR loading_cost_allocated_idr IS NULL OR loading_basis_grams IS NOT weight_grams)) OR
        EXISTS(SELECT 1 FROM weighing_loaders WHERE weighing_id = NEW.id AND (share_amount_idr IS NULL OR allocation_order IS NULL OR member_count_snapshot IS NOT (SELECT COUNT(*) FROM weighing_loaders WHERE weighing_id = NEW.id))) OR
        NEW.loading_basis_grams IS NOT (SELECT SUM(weight_grams) FROM weighing_items WHERE weighing_id = NEW.id) OR
        NEW.total_purchase_idr IS NOT (SELECT SUM(purchase_amount_idr) FROM weighing_items WHERE weighing_id = NEW.id) OR
        NEW.total_loading_wage_idr IS NOT (SELECT SUM(loading_cost_allocated_idr) FROM weighing_items WHERE weighing_id = NEW.id) OR
        NEW.total_loading_wage_idr IS NOT (SELECT SUM(share_amount_idr) FROM weighing_loaders WHERE weighing_id = NEW.id)
    THEN RAISE(ABORT, 'Weighing allocations do not reconcile') END;
END;
SQL);
        foreach (['price_schedules', 'factory_price_schedules', 'sort_stock_movements', 'idempotency_requests'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $event) {
                DB::unprepared("CREATE TRIGGER {$table}_no_{$event} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, 'Append-only record'); END;");
            }
        }
        foreach (['weighing_items', 'weighing_loaders'] as $table) {
            foreach (['UPDATE', 'DELETE', 'INSERT'] as $event) {
                $row = $event === 'INSERT' ? 'NEW' : 'OLD';
                DB::unprepared("CREATE TRIGGER {$table}_freeze_{$event} BEFORE {$event} ON {$table} WHEN (SELECT status FROM weighings WHERE id = {$row}.weighing_id) != 'draft' BEGIN SELECT RAISE(ABORT, 'Posted detail is immutable'); END;");
            }
            DB::unprepared("CREATE TRIGGER {$table}_freeze_move BEFORE UPDATE ON {$table} WHEN NEW.weighing_id != OLD.weighing_id AND (SELECT status FROM weighings WHERE id = NEW.weighing_id) != 'draft' BEGIN SELECT RAISE(ABORT, 'Cannot move detail to posted weighing'); END;");
        }
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['idempotency_requests', 'sort_stock_movements', 'sort_lots', 'weighing_loaders', 'weighing_items', 'weighings', 'trips', 'factory_price_schedules', 'price_schedules', 'document_sequences'] as $table) {
                DB::statement("DROP TABLE IF EXISTS {$table}");
            }
        });
    }
};
