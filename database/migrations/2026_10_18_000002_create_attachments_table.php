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
        if (app(LegacyMySqlSchema::class)->adoptTable('attachments')) {
            return;
        }

        Schema::create('attachments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('entity_type');
            $table->bigInteger('entity_id');
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->unsignedBigInteger('uploaded_by');
            $table->dateTime('created_at');
            $table->unique(['storage_path'], 'attachments_unique_1');
            $table->index(['entity_type', 'entity_id'], 'attachments_entity');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `attachments` ADD CONSTRAINT `attachments_chk_1` CHECK (entity_type IN ( 'factory' , 'sort' , 'customer' , 'payroll' , 'receipt' , 'expense' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `attachments` ADD CONSTRAINT `attachments_chk_2` CHECK (size_bytes BETWEEN 1 AND 10485760)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
