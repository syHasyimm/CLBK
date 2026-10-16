<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'driver' CHECK (role IN ('manager', 'driver', 'cashier'))");
        DB::statement('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))');
        // Fondasi hanya menyediakan satu akun bootstrap. Pertahankan akun awal saat upgrade.
        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null) {
            DB::table('users')->where('id', $firstUser)->update(['role' => 'manager']);
        }

        DB::statement('CREATE TABLE customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, phone TEXT,
            address TEXT, farm_location TEXT, notes TEXT,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE loading_workers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, phone TEXT,
            address TEXT, joined_on DATE,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE vehicles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, plate_number TEXT NOT NULL UNIQUE,
            vehicle_type TEXT,
            capacity_grams INTEGER CHECK (capacity_grams IS NULL OR (typeof(capacity_grams) = \'integer\' AND capacity_grams > 0 AND capacity_grams <= 1000000000)),
            default_driver_id INTEGER REFERENCES users(id) ON DELETE RESTRICT,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE factories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, address TEXT,
            contact_name TEXT, phone TEXT,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement("CREATE TABLE cash_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('cash', 'bank')),
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )");
        DB::statement('CREATE TABLE expense_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE app_settings (
            key TEXT PRIMARY KEY NOT NULL,
            value_json TEXT NOT NULL CHECK (json_valid(value_json)),
            updated_by INTEGER REFERENCES users(id) ON DELETE RESTRICT,
            created_at DATETIME, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_id INTEGER REFERENCES users(id) ON DELETE RESTRICT,
            action TEXT NOT NULL, entity_type TEXT NOT NULL, entity_id TEXT NOT NULL,
            before_json TEXT CHECK (before_json IS NULL OR json_valid(before_json)),
            after_json TEXT CHECK (after_json IS NULL OR json_valid(after_json)),
            reason TEXT, occurred_at DATETIME NOT NULL
        )');
        DB::statement('CREATE INDEX audit_logs_entity_time ON audit_logs (entity_type, entity_id, occurred_at)');
        DB::statement("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit bersifat append-only'); END");
        DB::statement("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit bersifat append-only'); END");
        foreach (['customers', 'loading_workers', 'vehicles', 'factories', 'cash_accounts', 'expense_categories'] as $table) {
            DB::statement("CREATE INDEX {$table}_active_code ON {$table} (is_active, code)");
        }
        DB::statement('CREATE INDEX users_role_active ON users (role, is_active)');
    }

    public function down(): void
    {
        foreach (['audit_logs', 'app_settings', 'expense_categories', 'cash_accounts', 'factories', 'vehicles', 'loading_workers', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function ($table) {
            $table->dropIndex('users_role_active');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
