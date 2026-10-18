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
        if (app(LegacyMySqlSchema::class)->adoptTable('factory_sales')) {
            return;
        }

        Schema::create('factory_sales', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('workflow')->default('legacy');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('factory_id');
            $table->unsignedBigInteger('factory_price_schedule_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->bigInteger('initial_load_grams')->nullable();
            $table->bigInteger('sorted_out_grams')->nullable();
            $table->bigInteger('accepted_grams');
            $table->bigInteger('weight_difference_grams')->nullable();
            $table->unsignedBigInteger('variance_approved_by')->nullable();
            $table->bigInteger('variance_approved_version')->nullable();
            $table->longText('variance_reason')->nullable();
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
            $table->unique(['replaces_sale_id'], 'factory_sales_unique_6');
            $table->unique(['number'], 'factory_sales_unique_7');
            $table->index(['business_date', 'status'], 'factory_sales_date_status');
            $table->index(['trip_id', 'status'], 'factory_sales_trip_status');
            $table->index(['created_by', 'business_date', 'status'], 'vehicle_workflow_factory_sales_creator');
            $table->index(['vehicle_id', 'business_date', 'status'], 'vehicle_workflow_factory_sales_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_1` CHECK (workflow IN ( 'legacy' , 'direct' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_2` CHECK (initial_load_grams IS NULL OR ( ( initial_load_grams IS NOT NULL ) AND initial_load_grams BETWEEN 1 AND 1000000000 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_3` CHECK (sorted_out_grams IS NULL OR ( ( sorted_out_grams IS NOT NULL ) AND sorted_out_grams BETWEEN 0 AND initial_load_grams ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_4` CHECK (( accepted_grams IS NOT NULL ) AND accepted_grams BETWEEN 1 AND 1000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_5` CHECK (weight_difference_grams = initial_load_grams - sorted_out_grams - accepted_grams)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_6` CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr BETWEEN 1 AND 100000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_7` CHECK (( deduction_idr IS NOT NULL ) AND deduction_idr BETWEEN 0 AND 100000000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_8` CHECK (( revenue_idr IS NOT NULL ) AND revenue_idr >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_9` CHECK (purchase_cost_released_idr IS NULL OR ( ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_10` CHECK (loading_cost_released_idr IS NULL OR ( ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_11` CHECK (cost_status IN ( 'available' , 'unavailable' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_12` CHECK (status IN ( 'draft' , 'posted' , 'reversed' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_13` CHECK (( version IS NOT NULL ) AND version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_14` CHECK (deduction_idr = 0 OR deduction_reason IS NOT NULL AND length( trim( deduction_reason ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_15` CHECK (cost_status = 'unavailable' AND purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR cost_status = 'available' AND purchase_cost_released_idr IS NOT NULL AND loading_cost_released_idr IS NOT NULL)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_16` CHECK (status = 'draft' OR posted_at IS NOT NULL AND posted_by IS NOT NULL)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_17` CHECK (status != 'reversed' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND length( trim( reversal_reason ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_18` CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_chk_19` CHECK (workflow = 'legacy' AND initial_load_grams IS NOT NULL AND sorted_out_grams IS NOT NULL AND weight_difference_grams IS NOT NULL AND invoice_number IS NOT NULL OR workflow = 'direct' AND initial_load_grams IS NULL AND sorted_out_grams IS NULL AND weight_difference_grams IS NULL AND variance_approved_by IS NULL AND variance_approved_version IS NULL AND variance_reason IS NULL AND factory_price_schedule_id IS NOT NULL AND cost_status = 'unavailable')
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `one_active_factory_invoice` ON `factory_sales` ((CASE WHEN status IN ( 'draft' , 'posted' ) THEN `factory_id` ELSE NULL END), (CASE WHEN status IN ( 'draft' , 'posted' ) THEN `invoice_number` ELSE NULL END))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `one_factory_sale_per_trip` ON `factory_sales` ((CASE WHEN status IN ( 'draft' , 'posted' ) THEN `trip_id` ELSE NULL END))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
