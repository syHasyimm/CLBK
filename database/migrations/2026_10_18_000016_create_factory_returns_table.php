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
        if (app(LegacyMySqlSchema::class)->adoptTable('factory_returns')) {
            return;
        }

        Schema::create('factory_returns', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('factory_sale_id');
            $table->bigInteger('weight_grams');
            $table->bigInteger('purchase_cost_carried_idr')->nullable();
            $table->bigInteger('loading_cost_carried_idr')->nullable();
            $table->string('cost_status');
            $table->dateTime('received_at');
            $table->longText('notes')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['factory_sale_id'], 'factory_returns_unique_0');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `factory_returns` ADD CONSTRAINT `factory_returns_chk_1` CHECK (( weight_grams IS NOT NULL ) AND weight_grams > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_returns` ADD CONSTRAINT `factory_returns_chk_2` CHECK (cost_status IN ( 'available' , 'unavailable' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `factory_returns` ADD CONSTRAINT `factory_returns_chk_3` CHECK (cost_status = 'unavailable' AND purchase_cost_carried_idr IS NULL AND loading_cost_carried_idr IS NULL OR cost_status = 'available' AND ( purchase_cost_carried_idr IS NOT NULL ) AND purchase_cost_carried_idr >= 0 AND ( loading_cost_carried_idr IS NOT NULL ) AND loading_cost_carried_idr >= 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
