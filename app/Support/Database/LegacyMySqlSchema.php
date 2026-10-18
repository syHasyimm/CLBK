<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Read-only bridge for databases installed before the per-table migrations. */
final class LegacyMySqlSchema
{
    public const MIGRATION = '2026_10_17_000001_create_clbk_schema';

    // MySQL 8.4 metadata of the previous baseline, excluding rows and migration history.
    public const FINGERPRINT = '021f56bfeb318566db05c7bf56926e2c3348d578ba2675434b17152908c89e8f';

    public function recorded(): bool
    {
        return Schema::hasTable('migrations')
            && DB::table('migrations')->where('migration', self::MIGRATION)->exists();
    }

    public function adoptExistingSchema(): bool
    {
        if (! $this->recorded()) {
            return false;
        }
        if (! hash_equals(self::FINGERPRINT, app(MySqlSchemaMetadata::class)->fingerprint())
            || ! DB::table('database_write_locks')->where('id', 1)->exists()) {
            throw new RuntimeException('Schema lama tidak cocok atau belum lengkap. Migrasi dihentikan tanpa membuat ulang tabel; periksa schema atau pulihkan backup yang sesuai.');
        }

        return true;
    }

    public function adoptTable(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        if (! $this->adoptExistingSchema()) {
            throw new RuntimeException("Tabel {$table} sudah ada tanpa riwayat migrasi yang sesuai. Migrasi tidak akan menghapus atau menimpanya.");
        }

        return true;
    }
}
