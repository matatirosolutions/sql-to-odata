<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class DeleteQuery extends Query
{
    /**
     * @param string $filter OData $filter expression derived from WHERE.
     */
    public function __construct(
        string $entitySet,
        public readonly string $filter,
    ) {
        parent::__construct($entitySet);
    }
}
