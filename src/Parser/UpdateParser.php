<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use Matatirosoln\SqlToOdata\Query\UpdateQuery;
use Matatirosoln\SqlToOdata\Support\ValueCaster;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;

class UpdateParser
{
    public function parse(UpdateStatement $statement): UpdateQuery
    {
        $table = $statement->tables[0]->table ?? null;
        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        if (empty($statement->where)) {
            throw new ConversionException('UPDATE without a WHERE clause is not supported.');
        }

        $body = [];
        foreach ($statement->set as $assignment) {
            $body[$assignment->column] = ValueCaster::cast($assignment->value);
        }

        return new UpdateQuery(
            entitySet: trim($table, '`"\''),
            body: $body,
            filter: OdataFilterBuilder::build($statement->where),
        );
    }
}
