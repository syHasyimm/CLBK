<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptTable('job_batches')) {
            return;
        }

        Schema::create('job_batches', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->string('id');
            $table->string('name');
            $table->bigInteger('total_jobs');
            $table->bigInteger('pending_jobs');
            $table->bigInteger('failed_jobs');
            $table->longText('failed_job_ids');
            $table->longText('options')->nullable();
            $table->bigInteger('cancelled_at')->nullable();
            $table->bigInteger('created_at');
            $table->bigInteger('finished_at')->nullable();
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
