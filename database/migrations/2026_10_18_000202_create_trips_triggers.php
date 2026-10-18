<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptExistingSchema()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `departure_reconciliation`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `departure_reconciliation` BEFORE UPDATE ON `trips` FOR EACH ROW
            BEGIN
                IF OLD.status = 'loading' AND NEW.status = 'dispatched' THEN
                IF NEW.departed_at IS NULL OR NEW.manual_load_grams IS NOT NULL OR EXISTS ( SELECT 1 FROM weighings WHERE trip_id = NEW.id AND status = 'draft' ) OR NOT ( NEW.tbs_purchase_cost_snapshot_idr <=> ( SELECT SUM( i.purchase_amount_idr ) FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) ) OR NOT ( NEW.tbs_loading_cost_snapshot_idr <=> ( SELECT SUM( i.loading_cost_allocated_idr ) FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) ) OR ( NEW.cost_status = 'available' ) != EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Departure costs do not reconcile';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `dispatched_trip_costs_frozen`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `dispatched_trip_costs_frozen` BEFORE UPDATE ON `trips` FOR EACH ROW
            BEGIN
                IF OLD.departed_at IS NOT NULL AND ( NOT ( NEW.departed_at <=> OLD.departed_at ) OR NEW.vehicle_id != OLD.vehicle_id OR NEW.driver_id != OLD.driver_id OR NOT ( NEW.tbs_purchase_cost_snapshot_idr <=> OLD.tbs_purchase_cost_snapshot_idr ) OR NOT ( NEW.tbs_loading_cost_snapshot_idr <=> OLD.tbs_loading_cost_snapshot_idr ) OR NEW.cost_status != OLD.cost_status ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Departure costs are frozen';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
