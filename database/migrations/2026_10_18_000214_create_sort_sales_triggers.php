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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_sort_sale_dependencies`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_sort_sale_dependencies` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' AND NEW.status = 'reversed' THEN
                IF EXISTS ( SELECT 1 FROM sale_receipts WHERE sort_sale_id = OLD.id AND status = 'posted' ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse sale receipts first';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `reconcile_sort_post`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `reconcile_sort_post` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
                IF NOT ( NEW.manual_sold_grams <=> ( SELECT SUM( weight_grams ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) OR NOT ( NEW.revenue_idr <=> ( ( SELECT SUM( gross_revenue_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) - NEW.deduction_idr ) ) OR EXISTS ( SELECT 1 FROM sort_sale_items i JOIN sort_lots l ON l.id = i.lot_id WHERE i.sort_sale_id = NEW.id AND ( l.trip_id != NEW.trip_id OR i.sell_price_snapshot_idr != NEW.sell_price_snapshot_idr ) ) OR NEW.cost_status = 'available' AND ( EXISTS ( SELECT 1 FROM sort_sale_items WHERE sort_sale_id = NEW.id AND purchase_cost_released_idr IS NULL ) OR NOT ( NEW.purchase_cost_released_idr <=> ( SELECT SUM( purchase_cost_released_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) OR NOT ( NEW.loading_cost_released_idr <=> ( SELECT SUM( loading_cost_released_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) ) OR NEW.cost_status = 'unavailable' AND NOT EXISTS ( SELECT 1 FROM sort_sale_items WHERE sort_sale_id = NEW.id AND purchase_cost_released_idr IS NULL ) OR EXISTS ( SELECT 1 FROM sort_sale_items i WHERE i.sort_sale_id = NEW.id AND NOT EXISTS ( SELECT 1 FROM sort_stock_movements m WHERE m.sort_sale_item_id = i.id AND m.lot_id = i.lot_id AND m.quantity_delta_grams = - i.weight_grams AND ( m.purchase_cost_delta_idr <=> - i.purchase_cost_released_idr ) AND ( m.loading_cost_delta_idr <=> - i.loading_cost_released_idr ) ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sort sale does not reconcile';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_sales_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_sales_freeze` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' AND ( NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.buyer_name <=> OLD.buyer_name ) OR NOT ( NEW.buyer_contact <=> OLD.buyer_contact ) OR NOT ( NEW.manual_sold_grams <=> OLD.manual_sold_grams ) OR NOT ( NEW.sold_at <=> OLD.sold_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.sell_price_snapshot_idr <=> OLD.sell_price_snapshot_idr ) OR NOT ( NEW.deduction_idr <=> OLD.deduction_idr ) OR NOT ( NEW.deduction_reason <=> OLD.deduction_reason ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.correction_reason <=> OLD.correction_reason ) OR NOT ( NEW.revenue_idr <=> OLD.revenue_idr ) OR NOT ( NEW.purchase_cost_released_idr <=> OLD.purchase_cost_released_idr ) OR NOT ( NEW.loading_cost_released_idr <=> OLD.loading_cost_released_idr ) OR NOT ( NEW.cost_status <=> OLD.cost_status ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NOT ( NEW.replaces_sale_id <=> OLD.replaces_sale_id ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_sales_no_delete`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_sales_no_delete` BEFORE DELETE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_sales_protect_proof`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_sales_protect_proof` BEFORE DELETE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF EXISTS ( SELECT 1 FROM attachments WHERE entity_type = 'sort' AND entity_id = OLD.id ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale proof history cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_sales_start_draft`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_sales_start_draft` BEFORE INSERT ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale must start as draft';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_sales_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_sales_INSERT` BEFORE INSERT ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_sales_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_sales_UPDATE` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_sales_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_sales_freeze` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_sort_sales_post`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_sort_sales_post` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'direct' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
                IF NOT ( NEW.revenue_idr <=> ( ( NEW.manual_sold_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sale revenue does not reconcile';
                END IF;
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
