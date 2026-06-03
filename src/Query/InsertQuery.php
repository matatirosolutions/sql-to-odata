<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

class InsertQuery extends Query
{
    /**
     * @param array<string, mixed> $body Column-value pairs to POST.
     */
    public function __construct(
        string $entitySet,
        public readonly array $body,
    ) {
        parent::__construct($entitySet);
    }
}
