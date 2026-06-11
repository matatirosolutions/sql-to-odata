<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class SelectQuery extends Query
{
    /**
     * @param list<array{field: string, alias: string}> $columns
     *        Ordered list of selected columns: the OData field name and the
     *        SQL alias (e.g. id_1) that the query consumer (Doctrine) expects.
     *        Empty when the query is SELECT * or contains only aggregates.
     */
    public function __construct(
        string $entitySet,
        public readonly string $queryString,
        public readonly array $columns = [],
    ) {
        parent::__construct($entitySet);
    }
}
