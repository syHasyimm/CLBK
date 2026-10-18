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
        if (app(LegacyMySqlSchema::class)->adoptTable('sort_stock_movements')) {
            return;
        }

        Schema::create('sort_stock_movements', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('lot_id');
            $table->string('kind');
            $table->bigInteger('quantity_delta_grams');
            $table->bigInteger('purchase_cost_delta_idr')->nullable();
            $table->bigInteger('loading_cost_delta_idr')->nullable();
            $table->unsignedBigInteger('sort_sale_item_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->longText('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('created_by');
            $table->unique(['reversal_of_id'], 'sort_stock_movements_unique_2');
            $table->unique(['sort_sale_item_id'], 'sort_stock_movements_unique_3');
            $table->index(['lot_id', 'occurred_at'], 'sort_movements_lot_time');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_chk_1` CHECK (kind IN ( 'receipt' , 'sale' , 'adjustment' , 'reversal' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_chk_2` CHECK (( quantity_delta_grams IS NOT NULL ) AND quantity_delta_grams != 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_chk_3` CHECK (purchase_cost_delta_idr IS NULL AND loading_cost_delta_idr IS NULL OR ( purchase_cost_delta_idr IS NOT NULL ) AND ( loading_cost_delta_idr IS NOT NULL ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_chk_4` CHECK (kind = 'receipt' AND quantity_delta_grams > 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL OR kind = 'sale' AND quantity_delta_grams < 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NOT NULL OR kind = 'adjustment' AND quantity_delta_grams < 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0 OR kind = 'reversal' AND reversal_of_id IS NOT NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `one_sort_receipt` ON `sort_stock_movements` ((CASE WHEN kind = 'receipt' THEN `lot_id` ELSE NULL END))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
