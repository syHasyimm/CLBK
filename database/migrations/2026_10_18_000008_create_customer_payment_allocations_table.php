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
        if (app(LegacyMySqlSchema::class)->adoptTable('customer_payment_allocations')) {
            return;
        }

        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_payment_id');
            $table->unsignedBigInteger('weighing_id');
            $table->bigInteger('amount_idr');
            $table->unique(['customer_payment_id', 'weighing_id'], 'customer_payment_allocations_unique_1');
            $table->index(['weighing_id'], 'customer_payment_allocations_target');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `customer_payment_allocations` ADD CONSTRAINT `customer_payment_allocations_chk_1` CHECK (( amount_idr IS NOT NULL ) AND amount_idr > 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
