<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class UpdateQuery extends Query
{
    /**
     * @param array<string, mixed> $body  Column-value pairs to PATCH.
     * @param string               $filter OData $filter expression derived from WHERE.
     */
    public function __construct(
        string $entitySet,
        public readonly array $body,
        public readonly string $filter,
    ) {
        parent::__construct($entitySet);
    }
}
