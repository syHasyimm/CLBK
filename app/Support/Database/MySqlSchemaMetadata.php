<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

final class MySqlSchemaMetadata
{
    /** Compare definitions, excluding data, AUTO_INCREMENT counters and migration history. */
    public function fingerprint(): string
    {
        // The historical mutex has only a numeric column, so its inherited
        // table collation has no effect on its definition or behavior.
        $queries = [
            "SELECT TABLE_NAME, ENGINE, CASE WHEN TABLE_NAME='database_write_locks' THEN NULL ELSE TABLE_COLLATION END AS TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME<>'migrations'",
            "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'migrations'",
            "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, EXPRESSION, INDEX_TYPE, IS_VISIBLE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'migrations'",
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.ORDINAL_POSITION, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL',
            "SELECT t.TABLE_NAME, c.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS t JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME WHERE t.TABLE_SCHEMA=DATABASE() AND t.CONSTRAINT_TYPE='CHECK'",
            'SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_ORDER, ACTION_STATEMENT, ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()',
        ];
        $definitions = [];
        foreach ($queries as $query) {
            $rows = [];
            foreach (DB::select($query) as $row) {
                $values = array_map(fn ($value) => $value === null ? null : (string) $value, (array) $row);
                foreach (['CHECK_CLAUSE', 'ACTION_STATEMENT', 'EXPRESSION'] as $sql) {
                    if (isset($values[$sql])) {
                        $values[$sql] = preg_replace('/\s+/', ' ', trim($values[$sql]));
                    }
                }
                $rows[] = json_encode($values, JSON_THROW_ON_ERROR);
            }
            sort($rows, SORT_STRING);
            $definitions[] = $rows;
        }

        return hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR));
    }
}
