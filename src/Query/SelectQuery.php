<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class SelectQuery extends Query
{
    public function __construct(
        string $entitySet,
        public readonly string $queryString,
    ) {
        parent::__construct($entitySet);
    }
}
