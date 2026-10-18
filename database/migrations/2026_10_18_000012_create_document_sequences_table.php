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
        if (app(LegacyMySqlSchema::class)->adoptTable('document_sequences')) {
            return;
        }

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->string('name');
            $table->bigInteger('next_value');
            $table->primary(['name']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `document_sequences` ADD CONSTRAINT `document_sequences_chk_1` CHECK (( next_value IS NOT NULL ) AND next_value > 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
