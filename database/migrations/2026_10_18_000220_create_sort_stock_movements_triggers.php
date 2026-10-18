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

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_stock_movements_no_DELETE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_stock_movements_no_DELETE` BEFORE DELETE ON `sort_stock_movements` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `sort_stock_movements_no_UPDATE`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `sort_stock_movements_no_UPDATE` BEFORE UPDATE ON `sort_stock_movements` FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS `stock_movement_guard`');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `stock_movement_guard` BEFORE INSERT ON `sort_stock_movements` FOR EACH ROW
            BEGIN
                IF ( SELECT status FROM sort_lots WHERE id = NEW.lot_id ) != 'active' OR NEW.occurred_at < ( SELECT received_at FROM sort_lots WHERE id = NEW.lot_id ) OR NEW.occurred_at < COALESCE( ( SELECT MAX( occurred_at ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , NEW.occurred_at ) OR ( ( SELECT cost_status FROM sort_lots WHERE id = NEW.lot_id ) = 'available' ) != ( NEW.purchase_cost_delta_idr IS NOT NULL AND NEW.loading_cost_delta_idr IS NOT NULL ) OR COALESCE( ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.quantity_delta_grams NOT BETWEEN 0 AND ( SELECT initial_grams FROM sort_lots WHERE id = NEW.lot_id ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock balance';
                END IF;
                IF NEW.kind = 'receipt' AND NOT EXISTS ( SELECT 1 FROM sort_lots l WHERE l.id = NEW.lot_id AND NEW.quantity_delta_grams = l.initial_grams AND ( NEW.purchase_cost_delta_idr <=> l.initial_purchase_cost_idr ) AND ( NEW.loading_cost_delta_idr <=> l.initial_loading_cost_idr ) ) OR NEW.kind IN ( 'sale' , 'adjustment' ) AND ( NEW.purchase_cost_delta_idr > 0 OR NEW.loading_cost_delta_idr > 0 ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Stock movement does not match source';
                END IF;
                IF NEW.kind = 'reversal' AND NOT EXISTS ( SELECT 1 FROM sort_stock_movements m WHERE m.id = NEW.reversal_of_id AND m.kind != 'reversal' AND m.lot_id = NEW.lot_id AND NEW.quantity_delta_grams = - m.quantity_delta_grams AND ( NEW.purchase_cost_delta_idr <=> - m.purchase_cost_delta_idr ) AND ( NEW.loading_cost_delta_idr <=> - m.loading_cost_delta_idr ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock reversal';
                END IF;
                IF NEW.purchase_cost_delta_idr IS NOT NULL AND ( COALESCE( ( SELECT SUM( purchase_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.purchase_cost_delta_idr NOT BETWEEN 0 AND ( SELECT initial_purchase_cost_idr FROM sort_lots WHERE id = NEW.lot_id ) OR COALESCE( ( SELECT SUM( loading_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.loading_cost_delta_idr NOT BETWEEN 0 AND ( SELECT initial_loading_cost_idr FROM sort_lots WHERE id = NEW.lot_id ) ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock cost balance';
                END IF;
                IF COALESCE( ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.quantity_delta_grams = 0 AND NEW.purchase_cost_delta_idr IS NOT NULL AND ( COALESCE( ( SELECT SUM( purchase_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.purchase_cost_delta_idr != 0 OR COALESCE( ( SELECT SUM( loading_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.loading_cost_delta_idr != 0 ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Empty lot must have zero remaining cost';
                END IF;
            END
            SQL);

    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
