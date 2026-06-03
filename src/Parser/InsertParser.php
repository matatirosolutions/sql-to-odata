<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\InsertQuery;
use Matatirosoln\SqlToOdata\Support\ValueCaster;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;

class InsertParser
{
    public function parse(InsertStatement $statement): InsertQuery
    {
        $table = $statement->into->dest->table ?? null;
        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        $columns = $statement->into->columns ?? [];

        if (empty($columns)) {
            throw new ConversionException('INSERT without a column list is not supported.');
        }

        $rows = [];
        foreach ($statement->values as $valueRow) {
            $raw = $valueRow->raw ?? [];

            if (count($columns) !== count($raw)) {
                throw new ConversionException('Column and value counts do not match.');
            }

            $body = [];
            foreach ($columns as $index => $column) {
                $body[$column] = ValueCaster::cast($raw[$index]);
            }
            $rows[] = $body;
        }

        return new InsertQuery(
            entitySet: trim($table, '`"\''),
            rows: $rows,
        );
    }
}
