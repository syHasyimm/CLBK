<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['weighings', 'factory_sales', 'sort_sales'];

    public function up(): void
    {
        $this->transition(true);
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (DB::table($table)->where('workflow', 'direct')->exists()) {
                throw new RuntimeException('Cannot roll back vehicle workflow while direct transactions exist. Preserve these records and use a compatible migration.');
            }
        }
        $this->transition(false);
    }

    private function transition(bool $forward): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($forward) {
            DB::transaction(function () use ($forward) {
                $triggers = DB::select("SELECT name,sql FROM sqlite_master WHERE type='trigger'");
                foreach ($triggers as $trigger) {
                    DB::statement('DROP TRIGGER "'.$trigger->name.'"');
                }
                foreach (self::TABLES as $table) {
                    $this->rebuild($table, $forward);
                }
                foreach ($triggers as $trigger) {
                    if (str_starts_with($trigger->name, 'vehicle_workflow_')) {
                        continue;
                    }
                    $sql = $trigger->sql;
                    if (in_array($trigger->name, ['reconcile_sort_post', 'reconcile_factory_post'], true)) {
                        $sql = $forward
                            ? str_replace("WHEN OLD.status='draft' AND NEW.status='posted'", "WHEN NEW.workflow='legacy' AND OLD.status='draft' AND NEW.status='posted'", $sql)
                            : str_replace("WHEN NEW.workflow='legacy' AND OLD.status='draft' AND NEW.status='posted'", "WHEN OLD.status='draft' AND NEW.status='posted'", $sql);
                    }
                    DB::unprepared($sql);
                }
                if ($forward) {
                    $this->guards();
                }
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Vehicle workflow migration failed foreign key validation.');
                }
            });
        });
    }

    private function directColumns(): string
    {
        return "vehicle_id INTEGER NOT NULL REFERENCES vehicles(id) ON DELETE RESTRICT,\nworkflow TEXT NOT NULL DEFAULT 'legacy' CHECK(workflow IN ('legacy','direct')),\n";
    }

    private function workflowCheck(): string
    {
        return ",\nCHECK((workflow='legacy' AND trip_id IS NOT NULL) OR (workflow='direct' AND trip_id IS NULL))";
    }

    private function factoryCheck(): string
    {
        return ",\nCHECK(workflow='legacy' AND initial_load_grams IS NOT NULL AND sorted_out_grams IS NOT NULL AND weight_difference_grams IS NOT NULL AND invoice_number IS NOT NULL OR workflow='direct' AND initial_load_grams IS NULL AND sorted_out_grams IS NULL AND weight_difference_grams IS NULL AND variance_approved_by IS NULL AND variance_approved_version IS NULL AND variance_reason IS NULL AND factory_price_schedule_id IS NOT NULL AND cost_status='unavailable')";
    }

    private function sortCheck(): string
    {
        return ",\nCHECK(workflow='legacy' AND buyer_name IS NOT NULL OR workflow='direct' AND cost_status='unavailable')";
    }

    private function rebuild(string $table, bool $forward): void
    {
        $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');
        $columns = array_column(DB::select('PRAGMA table_info("'.$table.'")'), 'name');
        $indexes = DB::select("SELECT name,sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL", [$table]);
        $sequence = DB::table('sqlite_sequence')->where('name', $table)->value('seq');
        $beforeCount = DB::table($table)->count();
        $sql = $forward
            ? str_replace('trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT,', $this->directColumns().'trip_id INTEGER REFERENCES trips(id) ON DELETE RESTRICT,', $sql)
            : str_replace($this->directColumns().'trip_id INTEGER REFERENCES trips(id) ON DELETE RESTRICT,', 'trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE RESTRICT,', $sql);
        $specific = '';
        if ($table === 'factory_sales') {
            foreach (['invoice_number TEXT', 'initial_load_grams INTEGER', 'sorted_out_grams INTEGER', 'weight_difference_grams INTEGER'] as $definition) {
                $sql = $forward ? str_replace($definition.' NOT NULL', $definition, $sql) : str_replace($definition, $definition.' NOT NULL', $sql);
            }
            foreach (['initial_load_grams' => "typeof(initial_load_grams)='integer' AND initial_load_grams BETWEEN 1 AND 1000000000", 'sorted_out_grams' => "typeof(sorted_out_grams)='integer' AND sorted_out_grams BETWEEN 0 AND initial_load_grams"] as $column => $check) {
                $original = 'CHECK('.$check.')';
                $nullable = 'CHECK('.$column.' IS NULL OR ('.$check.'))';
                $sql = $forward ? str_replace($original, $nullable, $sql) : str_replace($nullable, $original, $sql);
            }
            $specific = $this->factoryCheck();
        } elseif ($table === 'sort_sales') {
            $sql = $forward ? str_replace('buyer_name TEXT NOT NULL', 'buyer_name TEXT', $sql) : str_replace('buyer_name TEXT', 'buyer_name TEXT NOT NULL', $sql);
            $specific = $this->sortCheck();
        }
        if ($forward) {
            $sql = substr(rtrim($sql), 0, -1).$this->workflowCheck().$specific."\n)";
        } else {
            $sql = str_replace([$this->workflowCheck(), $specific], '', $sql);
            $columns = array_diff($columns, ['vehicle_id', 'workflow']);
        }
        $temporary = $table.'_vehicle_transition';
        $sql = preg_replace('/^CREATE TABLE\s+["`]?'.preg_quote($table, '/').'["`]?\s*\(/i', 'CREATE TABLE '.$temporary.' (', $sql, 1, $replacements);
        if ($replacements !== 1 || ($forward && ! str_contains($sql, $this->directColumns()))) {
            throw new RuntimeException('Unexpected schema during vehicle migration: '.$table);
        }
        DB::unprepared($sql);
        $list = implode(',', array_map(fn ($column) => '"'.$column.'"', $columns));
        $extraColumns = $forward ? ',vehicle_id,workflow' : '';
        $extraValues = $forward ? ", (SELECT vehicle_id FROM trips WHERE id={$table}.trip_id), 'legacy'" : '';
        DB::statement("INSERT INTO {$temporary}({$list}{$extraColumns}) SELECT {$list}{$extraValues} FROM {$table}");
        if (DB::table($temporary)->count() !== $beforeCount) {
            throw new RuntimeException('Row count changed during vehicle migration: '.$table);
        }
        DB::statement('DROP TABLE "'.$table.'"');
        DB::statement('ALTER TABLE "'.$temporary.'" RENAME TO "'.$table.'"');
        foreach ($indexes as $index) {
            if (! str_starts_with($index->name, 'vehicle_workflow_')) {
                DB::unprepared($index->sql);
            }
        }
        if ($forward) {
            DB::statement("CREATE INDEX vehicle_workflow_{$table}_date ON {$table}(vehicle_id,business_date,status)");
            DB::statement("CREATE INDEX vehicle_workflow_{$table}_creator ON {$table}(created_by,business_date,status)");
        }
        // Rebuilding an AUTOINCREMENT table must not reuse an ID belonging to a deleted draft.
        if ($sequence !== null) {
            DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $sequence]);
        }
    }

    private function guards(): void
    {
        foreach (self::TABLES as $table) {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER vehicle_workflow_{$table}_{$event} BEFORE {$event} ON {$table}
WHEN NEW.workflow='legacy' AND NEW.vehicle_id IS NOT (SELECT vehicle_id FROM trips WHERE id=NEW.trip_id)
BEGIN SELECT RAISE(ABORT,'Legacy transaction vehicle must match its trip'); END;");
            }
            DB::unprepared("CREATE TRIGGER vehicle_workflow_{$table}_freeze BEFORE UPDATE ON {$table}
WHEN NEW.workflow!=OLD.workflow OR (OLD.status!='draft' AND (NEW.vehicle_id IS NOT OLD.vehicle_id OR NEW.trip_id IS NOT OLD.trip_id))
BEGIN SELECT RAISE(ABORT,'Transaction workflow and posted vehicle are immutable'); END;");
        }
        foreach (['factory_sales' => 'accepted_grams', 'sort_sales' => 'manual_sold_grams'] as $table => $weight) {
            DB::unprepared("CREATE TRIGGER vehicle_workflow_{$table}_post BEFORE UPDATE ON {$table}
WHEN NEW.workflow='direct' AND OLD.status='draft' AND NEW.status='posted'
BEGIN SELECT CASE WHEN NEW.revenue_idr IS NOT ((NEW.{$weight}*NEW.sell_price_snapshot_idr+500)/1000-NEW.deduction_idr)
THEN RAISE(ABORT,'Direct sale revenue does not reconcile') END; END;");
        }
        foreach (['INSERT', 'UPDATE'] as $event) {
            DB::unprepared("CREATE TRIGGER vehicle_workflow_factory_price_{$event} BEFORE {$event} ON factory_sales
WHEN NEW.workflow='direct' AND NOT EXISTS(SELECT 1 FROM factory_price_schedules p WHERE p.id=NEW.factory_price_schedule_id AND p.factory_id=NEW.factory_id AND p.tbs_sell_price_idr=NEW.sell_price_snapshot_idr AND p.effective_from<=NEW.sold_at)
BEGIN SELECT RAISE(ABORT,'Direct factory sale needs its matching price snapshot'); END;");
            DB::unprepared("CREATE TRIGGER vehicle_workflow_factory_return_{$event} BEFORE {$event} ON factory_returns
WHEN (SELECT workflow FROM factory_sales WHERE id=NEW.factory_sale_id)='direct'
BEGIN SELECT RAISE(ABORT,'Direct factory sales do not create return stock'); END;");
            DB::unprepared("CREATE TRIGGER vehicle_workflow_sort_item_{$event} BEFORE {$event} ON sort_sale_items
WHEN (SELECT workflow FROM sort_sales WHERE id=NEW.sort_sale_id)='direct'
BEGIN SELECT RAISE(ABORT,'Direct sort sales do not allocate FIFO'); END;");
            DB::unprepared("CREATE TRIGGER vehicle_workflow_sort_lot_{$event} BEFORE {$event} ON sort_lots
WHEN EXISTS(SELECT 1 FROM weighing_items i JOIN weighings w ON w.id=i.weighing_id WHERE i.id=NEW.source_weighing_item_id AND w.workflow='direct')
BEGIN SELECT RAISE(ABORT,'Direct weighings do not create stock'); END;");
        }
    }
};
