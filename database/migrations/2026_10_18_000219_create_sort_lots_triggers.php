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

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_lot_frozen`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_lot_frozen` BEFORE UPDATE ON `sort_lots` FOR EACH ROW
            BEGIN
                IF NEW.id != OLD.id OR NEW.source_type != OLD.source_type OR NOT ( NEW.source_weighing_item_id <=> OLD.source_weighing_item_id ) OR NOT ( NEW.source_factory_return_id <=> OLD.source_factory_return_id ) OR NEW.trip_id != OLD.trip_id OR NEW.received_at != OLD.received_at OR NEW.initial_grams != OLD.initial_grams OR NOT ( NEW.initial_purchase_cost_idr <=> OLD.initial_purchase_cost_idr ) OR NOT ( NEW.initial_loading_cost_idr <=> OLD.initial_loading_cost_idr ) OR NEW.cost_status != OLD.cost_status OR NOT ( OLD.status = 'active' AND NEW.status = 'voided' AND ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = OLD.id ) = 0 ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Source lot is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_lot_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_lot_INSERT` BEFORE INSERT ON `sort_lots` FOR EACH ROW
            BEGIN
                IF EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE i.id = NEW.source_weighing_item_id AND w.workflow = 'direct' ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct weighings do not create stock';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_lot_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_lot_UPDATE` BEFORE UPDATE ON `sort_lots` FOR EACH ROW
            BEGIN
                IF EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE i.id = NEW.source_weighing_item_id AND w.workflow = 'direct' ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct weighings do not create stock';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
