<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Query;

abstract class Query
{
    public function __construct(
        public readonly string $entitySet,
    ) {
    }
}
