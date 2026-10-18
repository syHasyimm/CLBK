<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptTable('trips')) {
            return;
        }

        Schema::create('trips', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('planned_factory_id')->nullable();
            $table->string('business_date');
            $table->string('status');
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->bigInteger('manual_load_grams')->nullable();
            $table->bigInteger('tbs_purchase_cost_snapshot_idr')->nullable();
            $table->bigInteger('tbs_loading_cost_snapshot_idr')->nullable();
            $table->string('cost_status')->default('unavailable');
            $table->bigInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['number'], 'trips_unique_3');
            $table->index(['driver_id', 'status'], 'trips_driver_status');
            $table->index(['vehicle_id', 'status', 'business_date'], 'trips_vehicle_status_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_1` CHECK (status IN ( 'loading' , 'dispatched' , 'factory_recorded' , 'closed' , 'cancelled' , 'correction_pending' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_2` CHECK (manual_load_grams IS NULL OR ( ( manual_load_grams IS NOT NULL ) AND manual_load_grams BETWEEN 1 AND 1000000000 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_3` CHECK (tbs_purchase_cost_snapshot_idr IS NULL OR ( ( tbs_purchase_cost_snapshot_idr IS NOT NULL ) AND tbs_purchase_cost_snapshot_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_4` CHECK (tbs_loading_cost_snapshot_idr IS NULL OR ( ( tbs_loading_cost_snapshot_idr IS NOT NULL ) AND tbs_loading_cost_snapshot_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_5` CHECK (cost_status IN ( 'available' , 'unavailable' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `trips` ADD CONSTRAINT `trips_chk_6` CHECK (( version IS NOT NULL ) AND version > 0)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `one_active_trip_per_vehicle` ON `trips` ((CASE WHEN status IN ( 'loading' , 'dispatched' ) THEN `vehicle_id` ELSE NULL END))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
