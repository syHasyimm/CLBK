<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->bigInteger('attempts');
            $table->bigInteger('reserved_at')->nullable();
            $table->bigInteger('available_at');
            $table->bigInteger('created_at');
            $table->index(['queue'], 'jobs_queue_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
