<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

/**
 * Represents a resolved OData entity key extracted from a SQL WHERE clause.
 *
 * Carries both the raw value and whether it requires quoting in the key-path
 * URL segment, since OData formatting differs by type:
 *   string / UUID  →  /EntitySet('value')
 *   integer        →  /EntitySet(42)
 */
final class KeyValue
{
    public function __construct(
        public readonly string $value,
        public readonly bool   $quoted,
    ) {}

    /**
     * Returns the parenthesised key-path segment ready to append to an entity-
     * set URL, e.g. "('08EC1E80-...')" or "(42)".
     */
    public function toUrlSegment(): string
    {
        return $this->quoted
            ? "('{$this->value}')"
            : "({$this->value})";
    }
}
