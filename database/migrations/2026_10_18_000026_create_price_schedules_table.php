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
        if (app(LegacyMySqlSchema::class)->adoptTable('price_schedules')) {
            return;
        }

        Schema::create('price_schedules', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('business_date');
            $table->dateTime('effective_from');
            $table->bigInteger('tbs_buy_price_idr');
            $table->bigInteger('sort_buy_price_idr');
            $table->bigInteger('loading_rate_idr');
            $table->longText('notes')->nullable();
            $table->unsignedBigInteger('published_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['effective_from'], 'price_schedules_unique_1');
            $table->index(['business_date', 'effective_from'], 'prices_date_effective');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `price_schedules` ADD CONSTRAINT `price_schedules_chk_1` CHECK (( tbs_buy_price_idr IS NOT NULL ) AND tbs_buy_price_idr BETWEEN 1 AND 100000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `price_schedules` ADD CONSTRAINT `price_schedules_chk_2` CHECK (( sort_buy_price_idr IS NOT NULL ) AND sort_buy_price_idr BETWEEN 1 AND 100000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `price_schedules` ADD CONSTRAINT `price_schedules_chk_3` CHECK (( loading_rate_idr IS NOT NULL ) AND loading_rate_idr BETWEEN 0 AND 100000000)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
