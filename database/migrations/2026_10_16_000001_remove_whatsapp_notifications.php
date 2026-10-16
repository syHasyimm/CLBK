<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite'
            && version_compare(DB::selectOne('SELECT sqlite_version() AS version')->version, '3.35.0', '<')) {
            // Native DROP COLUMN preserves the original customer CHECK constraints, indexes and triggers.
            throw new RuntimeException('Penghapusan WhatsApp memerlukan SQLite 3.35 atau lebih baru.');
        }

        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_attempts');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('pickup_notices');
        Schema::dropIfExists('whatsapp_settings');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'whatsapp_enabled']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Penghapusan WhatsApp tidak dapat dibatalkan. Pulihkan cadangan database jika data integrasi lama diperlukan.');
    }
};
