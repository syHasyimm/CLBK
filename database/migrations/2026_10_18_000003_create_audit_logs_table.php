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
        if (app(LegacyMySqlSchema::class)->adoptTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->longText('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'audit_logs_entity_time');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_chk_1` CHECK (before_json IS NULL OR json_valid( before_json ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_chk_2` CHECK (after_json IS NULL OR json_valid( after_json ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
