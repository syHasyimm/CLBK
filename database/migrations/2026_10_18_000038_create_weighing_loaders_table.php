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
        if (app(LegacyMySqlSchema::class)->adoptTable('weighing_loaders')) {
            return;
        }

        Schema::create('weighing_loaders', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('weighing_id');
            $table->unsignedBigInteger('worker_id');
            $table->bigInteger('member_count_snapshot')->nullable();
            $table->bigInteger('share_amount_idr')->nullable();
            $table->bigInteger('allocation_order')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['weighing_id', 'worker_id'], 'weighing_loaders_unique_1');
            $table->index(['worker_id', 'weighing_id'], 'loaders_worker_weighing');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_loaders` ADD CONSTRAINT `weighing_loaders_chk_1` CHECK (member_count_snapshot IS NULL OR ( ( member_count_snapshot IS NOT NULL ) AND member_count_snapshot > 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_loaders` ADD CONSTRAINT `weighing_loaders_chk_2` CHECK (share_amount_idr IS NULL OR ( ( share_amount_idr IS NOT NULL ) AND share_amount_idr >= 0 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `weighing_loaders` ADD CONSTRAINT `weighing_loaders_chk_3` CHECK (allocation_order IS NULL OR ( ( allocation_order IS NOT NULL ) AND allocation_order > 0 ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
