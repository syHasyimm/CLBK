<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->string('id');
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->longText('payload');
            $table->bigInteger('last_activity');
            $table->primary(['id']);
            $table->index(['last_activity'], 'sessions_last_activity_index');
            $table->index(['user_id'], 'sessions_user_id_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
