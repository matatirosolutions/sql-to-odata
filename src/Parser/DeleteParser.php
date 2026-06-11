<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use Matatirosoln\SqlToOdata\Query\DeleteQuery;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;

readonly class DeleteParser
{
    public function __construct(
        private OdataFilterBuilder $filterBuilder
    ) { }

    public function parse(DeleteStatement $statement): DeleteQuery
    {
        $table = $statement->from[0]->table ?? null;
        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        if (empty($statement->where)) {
            throw new ConversionException('DELETE without a WHERE clause is not supported.');
        }

        return new DeleteQuery(
            entitySet: trim($table, '`"\''),
            filter: $this->filterBuilder->build($statement->where),
        );
    }
}
