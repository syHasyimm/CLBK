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
        if (app(LegacyMySqlSchema::class)->adoptTable('idempotency_requests')) {
            return;
        }

        Schema::create('idempotency_requests', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id');
            $table->string('operation');
            $table->string('key');
            $table->string('request_hash');
            $table->bigInteger('response_reference');
            $table->string('status');
            $table->dateTime('created_at');
            $table->unique(['actor_id', 'operation', 'key'], 'idempotency_requests_unique_0');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `idempotency_requests` ADD CONSTRAINT `idempotency_requests_chk_1` CHECK (status = 'completed')
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
