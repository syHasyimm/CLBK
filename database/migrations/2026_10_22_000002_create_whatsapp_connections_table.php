<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');
            $table->unsignedBigInteger('id')->primary();
            $table->text('token');
            $table->uuid('revision');
            $table->string('status', 30)->default('unchecked');
            $table->string('message', 255)->nullable();
            $table->string('device_number', 25)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Konfigurasi WhatsApp dipertahankan. Gunakan backup/restore untuk pemulihan.');
    }
};
