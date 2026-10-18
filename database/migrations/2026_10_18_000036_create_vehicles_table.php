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
        if (app(LegacyMySqlSchema::class)->adoptTable('vehicles')) {
            return;
        }

        Schema::create('vehicles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('code');
            $table->string('plate_number');
            $table->string('vehicle_type')->nullable();
            $table->bigInteger('capacity_grams')->nullable();
            $table->unsignedBigInteger('default_driver_id')->nullable();
            $table->bigInteger('is_active')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['plate_number'], 'vehicles_unique_1');
            $table->unique(['code'], 'vehicles_unique_2');
            $table->index(['is_active', 'code'], 'vehicles_active_code');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_chk_1` CHECK (capacity_grams IS NULL OR ( ( capacity_grams IS NOT NULL ) AND capacity_grams > 0 AND capacity_grams <= 1000000000 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_chk_2` CHECK (is_active IN ( 0 , 1 ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
