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
        if (app(LegacyMySqlSchema::class)->adoptTable('loading_workers')) {
            return;
        }

        Schema::create('loading_workers', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('code');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->longText('address')->nullable();
            $table->date('joined_on')->nullable();
            $table->bigInteger('is_active')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['code'], 'loading_workers_unique_1');
            $table->index(['is_active', 'code'], 'loading_workers_active_code');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `loading_workers` ADD CONSTRAINT `loading_workers_chk_1` CHECK (is_active IN ( 0 , 1 ))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
