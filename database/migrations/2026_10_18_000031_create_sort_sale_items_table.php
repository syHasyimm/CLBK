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
        if (app(LegacyMySqlSchema::class)->adoptTable('sort_sale_items')) {
            return;
        }

        Schema::create('sort_sale_items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('sort_sale_id');
            $table->unsignedBigInteger('lot_id');
            $table->bigInteger('weight_grams');
            $table->bigInteger('sell_price_snapshot_idr');
            $table->bigInteger('gross_revenue_idr');
            $table->bigInteger('purchase_cost_released_idr')->nullable();
            $table->bigInteger('loading_cost_released_idr')->nullable();
            $table->unique(['sort_sale_id', 'lot_id'], 'sort_sale_items_unique_0');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_chk_1` CHECK (( weight_grams IS NOT NULL ) AND weight_grams > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_chk_2` CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_chk_3` CHECK (( gross_revenue_idr IS NOT NULL ) AND gross_revenue_idr >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_chk_4` CHECK (purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 AND ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
