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
        if (app(LegacyMySqlSchema::class)->adoptTable('weighings')) {
            return;
        }

        Schema::create('weighings', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('workflow')->default('legacy');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->dateTime('weighed_at');
            $table->string('business_date');
            $table->longText('backdate_reason')->nullable();
            $table->unsignedBigInteger('price_schedule_id')->nullable();
            $table->bigInteger('loading_rate_snapshot_idr')->nullable();
            $table->bigInteger('loading_basis_grams')->nullable();
            $table->bigInteger('total_purchase_idr')->nullable();
            $table->bigInteger('total_loading_wage_idr')->nullable();
            $table->string('status');
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->longText('void_reason')->nullable();
            $table->unsignedBigInteger('replaces_weighing_id')->nullable();
            $table->bigInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['number'], 'weighings_unique_4');
            $table->index(['created_by', 'business_date', 'status'], 'vehicle_workflow_weighings_creator');
            $table->index(['vehicle_id', 'business_date', 'status'], 'vehicle_workflow_weighings_date');
            $table->index(['customer_id', 'status', 'weighed_at'], 'weighings_customer_status_date');
            $table->index(['trip_id', 'status'], 'weighings_trip_status');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_1` CHECK (workflow IN ( 'legacy' , 'direct' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_2` CHECK (loading_rate_snapshot_idr IS NULL OR ( ( loading_rate_snapshot_idr IS NOT NULL ) AND loading_rate_snapshot_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_3` CHECK (loading_basis_grams IS NULL OR ( ( loading_basis_grams IS NOT NULL ) AND loading_basis_grams > 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_4` CHECK (total_purchase_idr IS NULL OR ( ( total_purchase_idr IS NOT NULL ) AND total_purchase_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_5` CHECK (total_loading_wage_idr IS NULL OR ( ( total_loading_wage_idr IS NOT NULL ) AND total_loading_wage_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_6` CHECK (status IN ( 'draft' , 'posted' , 'voided' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_7` CHECK (( version IS NOT NULL ) AND version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_8` CHECK (status = 'draft' OR ( price_schedule_id IS NOT NULL AND loading_rate_snapshot_idr IS NOT NULL AND loading_basis_grams IS NOT NULL AND total_purchase_idr IS NOT NULL AND total_loading_wage_idr IS NOT NULL AND posted_at IS NOT NULL AND posted_by IS NOT NULL ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_9` CHECK (status != 'voided' OR ( voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL AND length( trim( void_reason ) ) > 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighings` ADD CONSTRAINT `weighings_chk_10` CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
