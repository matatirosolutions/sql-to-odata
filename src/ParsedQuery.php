<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata;

final class ParsedQuery
{
    public function __construct(
        public readonly string $entitySet,
        public readonly string $queryString,
    ) {
    }
}
