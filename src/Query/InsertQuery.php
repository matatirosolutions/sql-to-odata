<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class InsertQuery extends Query
{
    /**
     * @param array<array<string, mixed>> $rows One or more rows of column-value pairs to POST.
     */
    public function __construct(
        string $entitySet,
        public readonly array $rows,
    ) {
        parent::__construct($entitySet);
    }
}
