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
        if (app(LegacyMySqlSchema::class)->adoptTable('sort_sales')) {
            return;
        }

        Schema::create('sort_sales', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('workflow')->default('legacy');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_contact')->nullable();
            $table->bigInteger('manual_sold_grams');
            $table->dateTime('sold_at');
            $table->string('business_date');
            $table->bigInteger('sell_price_snapshot_idr');
            $table->bigInteger('deduction_idr');
            $table->longText('deduction_reason')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('correction_reason')->nullable();
            $table->bigInteger('revenue_idr');
            $table->bigInteger('purchase_cost_released_idr')->nullable();
            $table->bigInteger('loading_cost_released_idr')->nullable();
            $table->string('cost_status')->default('unavailable');
            $table->string('status');
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_business_date')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->longText('reversal_reason')->nullable();
            $table->unsignedBigInteger('replaces_sale_id')->nullable();
            $table->bigInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['replaces_sale_id'], 'sort_sales_unique_4');
            $table->unique(['number'], 'sort_sales_unique_5');
            $table->index(['business_date', 'status'], 'sort_sales_date_status');
            $table->index(['trip_id', 'status'], 'sort_sales_trip_status');
            $table->index(['created_by', 'business_date', 'status'], 'vehicle_workflow_sort_sales_creator');
            $table->index(['vehicle_id', 'business_date', 'status'], 'vehicle_workflow_sort_sales_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_1` CHECK (workflow IN ( 'legacy' , 'direct' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_2` CHECK (( manual_sold_grams IS NOT NULL ) AND manual_sold_grams BETWEEN 1 AND 1000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_3` CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr BETWEEN 1 AND 100000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_4` CHECK (( deduction_idr IS NOT NULL ) AND deduction_idr BETWEEN 0 AND 100000000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_5` CHECK (( revenue_idr IS NOT NULL ) AND revenue_idr >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_6` CHECK (purchase_cost_released_idr IS NULL OR ( ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_7` CHECK (loading_cost_released_idr IS NULL OR ( ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_8` CHECK (cost_status IN ( 'available' , 'unavailable' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_9` CHECK (status IN ( 'draft' , 'posted' , 'reversed' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_10` CHECK (( version IS NOT NULL ) AND version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_11` CHECK (deduction_idr = 0 OR deduction_reason IS NOT NULL AND length( trim( deduction_reason ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_12` CHECK (cost_status = 'unavailable' AND purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR cost_status = 'available' AND purchase_cost_released_idr IS NOT NULL AND loading_cost_released_idr IS NOT NULL)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_13` CHECK (status = 'draft' OR posted_at IS NOT NULL AND posted_by IS NOT NULL)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_14` CHECK (status != 'reversed' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND length( trim( reversal_reason ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_15` CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_chk_16` CHECK (workflow = 'legacy' AND buyer_name IS NOT NULL OR workflow = 'direct' AND cost_status = 'unavailable')
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
