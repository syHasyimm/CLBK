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
        if (app(LegacyMySqlSchema::class)->adoptTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email');
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('role')->default('driver');
            $table->bigInteger('is_active')->default(1);
            $table->unsignedBigInteger('assigned_vehicle_id')->nullable();
            $table->bigInteger('vehicle_assignment_version')->default(1);
            $table->index(['assigned_vehicle_id'], 'users_assigned_vehicle');
            $table->unique(['email'], 'users_email_unique');
            $table->index(['role', 'is_active'], 'users_role_active');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `users` ADD CONSTRAINT `users_chk_1` CHECK (role IN ( 'manager' , 'driver' , 'cashier' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `users` ADD CONSTRAINT `users_chk_2` CHECK (is_active IN ( 0 , 1 ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `users` ADD CONSTRAINT `users_chk_3` CHECK (assigned_vehicle_id IS NULL OR role = 'driver')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `users` ADD CONSTRAINT `users_chk_4` CHECK (( vehicle_assignment_version IS NOT NULL ) AND vehicle_assignment_version >= 1)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
