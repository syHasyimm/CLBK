<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE sale_receipts ADD COLUMN is_automatic INTEGER NOT NULL DEFAULT 0 CHECK(is_automatic IN (0,1));
CREATE UNIQUE INDEX sale_receipts_automatic_factory ON sale_receipts(factory_sale_id) WHERE is_automatic=1;
CREATE UNIQUE INDEX sale_receipts_automatic_sort ON sale_receipts(sort_sale_id) WHERE is_automatic=1;
CREATE TRIGGER sale_receipts_automatic_frozen BEFORE UPDATE OF is_automatic ON sale_receipts
WHEN NEW.is_automatic IS NOT OLD.is_automatic
BEGIN SELECT RAISE(ABORT,'Automatic sale receipt provenance is immutable'); END;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER sale_receipts_automatic_frozen;
DROP INDEX sale_receipts_automatic_factory;
DROP INDEX sale_receipts_automatic_sort;
ALTER TABLE sale_receipts DROP COLUMN is_automatic;
SQL);
    }
};
