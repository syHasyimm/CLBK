<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Native ALTER preserves SQLite business triggers and historical foreign keys.
        DB::statement("ALTER TABLE users ADD COLUMN assigned_vehicle_id INTEGER REFERENCES vehicles(id) ON DELETE RESTRICT CHECK (assigned_vehicle_id IS NULL OR role = 'driver')");
        DB::statement("ALTER TABLE users ADD COLUMN vehicle_assignment_version INTEGER NOT NULL DEFAULT 1 CHECK (typeof(vehicle_assignment_version) = 'integer' AND vehicle_assignment_version >= 1)");
        DB::statement('CREATE INDEX users_assigned_vehicle ON users(assigned_vehicle_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_assigned_vehicle');
        DB::statement('ALTER TABLE users DROP COLUMN assigned_vehicle_id');
        DB::statement('ALTER TABLE users DROP COLUMN vehicle_assignment_version');
    }
};
