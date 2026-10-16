<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
        });
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->text('token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('device', 30)->nullable();
            $table->string('connection_status', 30)->default('unknown');
            $table->timestamp('checked_at')->nullable();
            $table->boolean('notify_weighing')->default(true);
            $table->boolean('notify_payment')->default(true);
            $table->boolean('notify_correction')->default(true);
            $table->text('pickup_location')->nullable();
            $table->string('service_hours', 255)->nullable();
            $table->text('weighing_template')->nullable();
            $table->text('payment_template')->nullable();
            $table->text('pickup_template')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX whatsapp_settings_singleton ON whatsapp_settings ((1))');
        Schema::create('pickup_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_idr');
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('ready');
            $table->text('close_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['cash_account_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX pickup_notices_active_customer ON pickup_notices(customer_id) WHERE status = 'ready'");
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 180)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name', 150);
            $table->string('target', 20)->nullable();
            $table->string('kind', 30);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('source_version')->default(1);
            $table->text('body');
            $table->string('status', 30)->default('queued');
            $table->string('provider_id', 80)->nullable()->unique();
            $table->string('provider_state_id', 100)->nullable()->index();
            $table->string('provider_state', 30)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['source_type', 'source_id']);
        });
        Schema::create('whatsapp_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_message_id')->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_hash', 64)->unique();
            $table->string('provider_id', 80)->nullable()->index();
            $table->string('state_id', 100)->nullable()->index();
            $table->string('status', 30)->nullable();
            $table->string('state', 30)->nullable();
            $table->boolean('applied')->default(false);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_attempts');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('pickup_notices');
        Schema::dropIfExists('whatsapp_settings');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['whatsapp_number', 'whatsapp_enabled']));
    }
};
