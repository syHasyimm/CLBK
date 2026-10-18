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
        if (app(LegacyMySqlSchema::class)->adoptTable('sort_lots')) {
            return;
        }

        Schema::create('sort_lots', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('source_type');
            $table->unsignedBigInteger('source_weighing_item_id')->nullable();
            $table->unsignedBigInteger('source_factory_return_id')->nullable();
            $table->unsignedBigInteger('trip_id');
            $table->dateTime('received_at');
            $table->bigInteger('initial_grams');
            $table->bigInteger('initial_purchase_cost_idr')->nullable();
            $table->bigInteger('initial_loading_cost_idr')->nullable();
            $table->string('cost_status');
            $table->string('status');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['source_factory_return_id'], 'sort_lots_unique_1');
            $table->unique(['source_weighing_item_id'], 'sort_lots_unique_2');
            $table->index(['trip_id', 'status', 'received_at', 'id'], 'sort_lots_trip_fifo');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_1` CHECK (source_type IN ( 'INITIAL_SORT' , 'FACTORY_RETURN' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_2` CHECK (( initial_grams IS NOT NULL ) AND initial_grams BETWEEN 1 AND 1000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_3` CHECK (cost_status IN ( 'available' , 'unavailable' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_4` CHECK (status IN ( 'active' , 'voided' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_5` CHECK (source_type = 'INITIAL_SORT' AND source_weighing_item_id IS NOT NULL AND source_factory_return_id IS NULL OR source_type = 'FACTORY_RETURN' AND source_factory_return_id IS NOT NULL AND source_weighing_item_id IS NULL)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_chk_6` CHECK (cost_status = 'unavailable' AND initial_purchase_cost_idr IS NULL AND initial_loading_cost_idr IS NULL OR cost_status = 'available' AND ( initial_purchase_cost_idr IS NOT NULL ) AND initial_purchase_cost_idr >= 0 AND ( initial_loading_cost_idr IS NOT NULL ) AND initial_loading_cost_idr >= 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
