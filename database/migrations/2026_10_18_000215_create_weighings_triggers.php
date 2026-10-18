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

        DB::unprepared('DROP TRIGGER IF EXISTS `finance_weighing_dependencies`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `finance_weighing_dependencies` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' AND NEW.status = 'voided' THEN
                IF EXISTS ( SELECT 1 FROM customer_payment_allocations a JOIN customer_payments p ON p.id = a.customer_payment_id WHERE a.weighing_id = OLD.id AND p.status = 'posted' ) OR EXISTS ( SELECT 1 FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id = a.payroll_payment_id JOIN weighing_loaders l ON l.id = a.weighing_loader_id WHERE l.weighing_id = OLD.id AND p.status = 'posted' ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse customer and payroll payments first';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `freeze_posted_weighing`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `freeze_posted_weighing` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' AND ( NEW.customer_id != OLD.customer_id OR NEW.trip_id != OLD.trip_id OR NEW.number != OLD.number OR NEW.weighed_at != OLD.weighed_at OR NEW.business_date != OLD.business_date OR NOT ( NEW.price_schedule_id <=> OLD.price_schedule_id ) OR NOT ( NEW.loading_rate_snapshot_idr <=> OLD.loading_rate_snapshot_idr ) OR NOT ( NEW.loading_basis_grams <=> OLD.loading_basis_grams ) OR NOT ( NEW.total_purchase_idr <=> OLD.total_purchase_idr ) OR NOT ( NEW.total_loading_wage_idr <=> OLD.total_loading_wage_idr ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NEW.created_by != OLD.created_by OR NOT ( NEW.replaces_weighing_id <=> OLD.replaces_weighing_id ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'voided' ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted weighing is immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `no_delete_posted_weighing`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `no_delete_posted_weighing` BEFORE DELETE ON `weighings` FOR EACH ROW
            BEGIN
                IF OLD.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted weighing cannot be deleted';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `reconcile_weighing_on_post`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `reconcile_weighing_on_post` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF OLD.status = 'draft' AND NEW.status = 'posted' THEN
                IF ( SELECT COUNT( * ) FROM weighing_items WHERE weighing_id = NEW.id ) = 0 OR ( SELECT COUNT( * ) FROM weighing_loaders WHERE weighing_id = NEW.id ) = 0 OR EXISTS ( SELECT 1 FROM weighing_items WHERE weighing_id = NEW.id AND ( buy_price_snapshot_idr IS NULL OR purchase_amount_idr IS NULL OR loading_cost_allocated_idr IS NULL OR NOT ( loading_basis_grams <=> weight_grams ) ) ) OR EXISTS ( SELECT 1 FROM weighing_loaders WHERE weighing_id = NEW.id AND ( share_amount_idr IS NULL OR allocation_order IS NULL OR NOT ( member_count_snapshot <=> ( SELECT COUNT( * ) FROM weighing_loaders WHERE weighing_id = NEW.id ) ) ) ) OR NOT ( NEW.loading_basis_grams <=> ( SELECT SUM( weight_grams ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_purchase_idr <=> ( SELECT SUM( purchase_amount_idr ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_loading_wage_idr <=> ( SELECT SUM( loading_cost_allocated_idr ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_loading_wage_idr <=> ( SELECT SUM( share_amount_idr ) FROM weighing_loaders WHERE weighing_id = NEW.id ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Weighing allocations do not reconcile';
                END IF;
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_weighings_INSERT`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_weighings_INSERT` BEFORE INSERT ON `weighings` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_weighings_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_weighings_UPDATE` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `vehicle_workflow_weighings_freeze`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `vehicle_workflow_weighings_freeze` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_after_departure_delete`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_after_departure_delete` BEFORE DELETE ON `weighings` FOR EACH ROW
            BEGIN
                IF ( SELECT departed_at FROM trips WHERE id = OLD.trip_id ) IS NOT NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dispatched ticket is frozen';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_after_departure_insert`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_after_departure_insert` BEFORE INSERT ON `weighings` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM trips WHERE id = NEW.trip_id ) != 'loading' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Trip no longer accepts weighings';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighing_after_departure_update`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighing_after_departure_update` BEFORE UPDATE ON `weighings` FOR EACH ROW
            BEGIN
                IF ( SELECT departed_at FROM trips WHERE id = OLD.trip_id ) IS NOT NULL OR ( SELECT status FROM trips WHERE id = NEW.trip_id ) != 'loading' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dispatched ticket is frozen';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `weighings_must_start_as_draft`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `weighings_must_start_as_draft` BEFORE INSERT ON `weighings` FOR EACH ROW
            BEGIN
                IF NEW.status != 'draft' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Weighing must start as draft';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
