<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key', 190)->unique();
            $table->string('event', 40);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name', 150);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 80);
            $table->string('target', 20)->nullable();
            $table->text('body');
            $table->string('status', 30);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_id', 100)->nullable()->unique();
            $table->string('state_id', 150)->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('whatsapp_attempts', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');
            $table->id();
            $table->foreignId('whatsapp_message_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 30);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('finished_at')->nullable();
            $table->unique(['whatsapp_message_id', 'number']);
        });

        // Callbacks may arrive before the sending worker has saved the provider ID.
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('provider_id', 100)->nullable()->index();
            $table->string('state_id', 150)->nullable()->index();
            $table->string('status', 40)->nullable();
            $table->string('state', 40)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Riwayat notifikasi dipertahankan. Gunakan backup/restore untuk pemulihan.');
    }
};
