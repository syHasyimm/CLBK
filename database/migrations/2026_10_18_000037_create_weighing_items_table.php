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
        if (app(LegacyMySqlSchema::class)->adoptTable('weighing_items')) {
            return;
        }

        Schema::create('weighing_items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('weighing_id');
            $table->string('fruit_type');
            $table->bigInteger('weight_grams');
            $table->bigInteger('buy_price_snapshot_idr')->nullable();
            $table->bigInteger('purchase_amount_idr')->nullable();
            $table->bigInteger('loading_basis_grams')->nullable();
            $table->bigInteger('loading_cost_allocated_idr')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['weighing_id', 'fruit_type'], 'weighing_items_unique_0');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_1` CHECK (fruit_type IN ( 'TBS' , 'SORT' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_2` CHECK (( weight_grams IS NOT NULL ) AND weight_grams BETWEEN 1 AND 1000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_3` CHECK (buy_price_snapshot_idr IS NULL OR ( ( buy_price_snapshot_idr IS NOT NULL ) AND buy_price_snapshot_idr > 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_4` CHECK (purchase_amount_idr IS NULL OR ( ( purchase_amount_idr IS NOT NULL ) AND purchase_amount_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_5` CHECK (loading_basis_grams IS NULL OR ( ( loading_basis_grams IS NOT NULL ) AND loading_basis_grams > 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_chk_6` CHECK (loading_cost_allocated_idr IS NULL OR ( ( loading_cost_allocated_idr IS NOT NULL ) AND loading_cost_allocated_idr >= 0 ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
