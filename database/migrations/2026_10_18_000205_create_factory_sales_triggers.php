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

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_sales_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_sales_freeze` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' AND ( NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.factory_id <=> OLD.factory_id ) OR NOT ( NEW.factory_price_schedule_id <=> OLD.factory_price_schedule_id ) OR NOT ( NEW.invoice_number <=> OLD.invoice_number ) OR NOT ( NEW.initial_load_grams <=> OLD.initial_load_grams ) OR NOT ( NEW.sorted_out_grams <=> OLD.sorted_out_grams ) OR NOT ( NEW.accepted_grams <=> OLD.accepted_grams ) OR NOT ( NEW.weight_difference_grams <=> OLD.weight_difference_grams ) OR NOT ( NEW.variance_approved_by <=> OLD.variance_approved_by ) OR NOT ( NEW.variance_approved_version <=> OLD.variance_approved_version ) OR NOT ( NEW.variance_reason <=> OLD.variance_reason ) OR NOT ( NEW.sold_at <=> OLD.sold_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.sell_price_snapshot_idr <=> OLD.sell_price_snapshot_idr ) OR NOT ( NEW.deduction_idr <=> OLD.deduction_idr ) OR NOT ( NEW.deduction_reason <=> OLD.deduction_reason ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.correction_reason <=> OLD.correction_reason ) OR NOT ( NEW.revenue_idr <=> OLD.revenue_idr ) OR NOT ( NEW.purchase_cost_released_idr <=> OLD.purchase_cost_released_idr ) OR NOT ( NEW.loading_cost_released_idr <=> OLD.loading_cost_released_idr ) OR NOT ( NEW.cost_status <=> OLD.cost_status ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NOT ( NEW.replaces_sale_id <=> OLD.replaces_sale_id ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_sales_no_delete`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_sales_no_delete` BEFORE DELETE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_sales_protect_proof`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_sales_protect_proof` BEFORE DELETE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF EXISTS ( SELECT 1 FROM attachments WHERE entity_type = 'factory' AND entity_id = OLD.id ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale proof history cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `factory_sales_start_draft`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `factory_sales_start_draft` BEFORE INSERT ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale must start as draft';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_factory_sale_dependencies`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_factory_sale_dependencies` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' AND NEW.status = 'reversed' THEN
                IF EXISTS ( SELECT 1 FROM sale_receipts WHERE factory_sale_id = OLD.id AND status = 'posted' ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse sale receipts first';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `reconcile_factory_post`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `reconcile_factory_post` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
                IF ( SELECT status FROM trips WHERE id = NEW.trip_id ) NOT IN ( 'dispatched' , 'correction_pending' ) OR NOT ( NEW.revenue_idr <=> ( ( NEW.accepted_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) OR NOT ( NEW.cost_status <=> ( SELECT cost_status FROM trips WHERE id = NEW.trip_id ) ) OR ( NEW.sorted_out_grams > 0 ) != EXISTS ( SELECT 1 FROM factory_returns WHERE factory_sale_id = NEW.id AND weight_grams = NEW.sorted_out_grams ) OR NEW.cost_status = 'available' AND ( NOT ( NEW.purchase_cost_released_idr <=> ( ( SELECT tbs_purchase_cost_snapshot_idr FROM trips WHERE id = NEW.trip_id ) - COALESCE( ( SELECT purchase_cost_carried_idr FROM factory_returns WHERE factory_sale_id = NEW.id ) , 0 ) ) ) OR NOT ( NEW.loading_cost_released_idr <=> ( ( SELECT tbs_loading_cost_snapshot_idr FROM trips WHERE id = NEW.trip_id ) - COALESCE( ( SELECT loading_cost_carried_idr FROM factory_returns WHERE factory_sale_id = NEW.id ) , 0 ) ) ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Factory sale does not reconcile';
                END IF;
                IF ( NEW.weight_difference_grams < 0 OR NEW.weight_difference_grams * 10000 > NEW.initial_load_grams * 200 ) AND ( NOT ( NEW.variance_approved_version <=> OLD.version ) OR NEW.variance_reason IS NULL OR length( trim( NEW.variance_reason ) ) = 0 OR NOT EXISTS ( SELECT 1 FROM users WHERE id = NEW.variance_approved_by AND role = 'manager' AND is_active = 1 ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Factory variance needs approval';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_price_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_price_INSERT` BEFORE INSERT ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'direct' AND NOT EXISTS ( SELECT 1 FROM factory_price_schedules p WHERE p.id = NEW.factory_price_schedule_id AND p.factory_id = NEW.factory_id AND p.tbs_sell_price_idr = NEW.sell_price_snapshot_idr AND p.effective_from <= NEW.sold_at ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sale needs its matching price snapshot';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_price_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_price_UPDATE` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'direct' AND NOT EXISTS ( SELECT 1 FROM factory_price_schedules p WHERE p.id = NEW.factory_price_schedule_id AND p.factory_id = NEW.factory_id AND p.tbs_sell_price_idr = NEW.sell_price_snapshot_idr AND p.effective_from <= NEW.sold_at ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sale needs its matching price snapshot';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_sales_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_sales_INSERT` BEFORE INSERT ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_sales_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_sales_UPDATE` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_sales_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_sales_freeze` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_factory_sales_post`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_factory_sales_post` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'direct' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
                IF NOT ( NEW.revenue_idr <=> ( ( NEW.accepted_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) THEN
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
