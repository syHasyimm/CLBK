<?php

namespace App\Support\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

/** Keep reference search case-insensitive without weakening binary unique keys/enums. */
final class MySqlQueryGrammar extends MySqlGrammar
{
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower($where['operator']);
        if (in_array($operator, ['like', 'not like'], true)) {
            return 'LOWER('.$this->wrap($where['column']).') '.$operator.' LOWER('.$this->parameter($where['value']).") ESCAPE ''";
        }

        return parent::whereBasic($query, $where);
    }
}
