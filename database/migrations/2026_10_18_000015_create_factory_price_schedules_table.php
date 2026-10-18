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
        if (app(LegacyMySqlSchema::class)->adoptTable('factory_price_schedules')) {
            return;
        }

        Schema::create('factory_price_schedules', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('factory_id');
            $table->dateTime('effective_from');
            $table->bigInteger('tbs_sell_price_idr');
            $table->unsignedBigInteger('published_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['factory_id', 'effective_from'], 'factory_price_schedules_unique_0');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `factory_price_schedules` ADD CONSTRAINT `factory_price_schedules_chk_1` CHECK (( tbs_sell_price_idr IS NOT NULL ) AND tbs_sell_price_idr BETWEEN 1 AND 100000000)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
