<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;

class OdataExpandBuilder
{
    /**
     * Build an OData $expand value from an array of JOIN clauses.
     *
     * Columns in $selectExprs that are qualified with a joined table name or alias
     * are promoted into nested $select options within the expand, e.g.:
     *   Orders($select=OrderDate,Total)
     *
     * @param JoinKeyword[] $joins
     * @param Expression[]  $selectExprs
     */
    public static function build(array $joins, array $selectExprs): string
    {
        $expands = [];

        foreach ($joins as $join) {
            $tableName = $join->expr->table;
            $alias     = $join->expr->alias ?? null;

            $columns = self::columnsForTable($selectExprs, $tableName, $alias);

            $expands[] = empty($columns)
                ? $tableName
                : $tableName . '($select=' . implode(',', $columns) . ')';
        }

        return implode(',', $expands);
    }

    /**
     * Return the column names from $selectExprs that belong to the given table.
     *
     * @param Expression[] $selectExprs
     * @return string[]
     */
    public static function columnsForTable(array $selectExprs, string $tableName, ?string $alias): array
    {
        $columns = [];

        foreach ($selectExprs as $expr) {
            $qualifier = $expr->table ?? '';

            if ($qualifier === '' || $expr->column === null || $expr->column === '*') {
                continue;
            }

            if ($qualifier === $tableName || ($alias !== null && $qualifier === $alias)) {
                $columns[] = $expr->column;
            }
        }

        return $columns;
    }
}
