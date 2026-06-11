<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

use PhpMyAdmin\SqlParser\Components\Condition;

class WhereKeyExtractor
{
    private const string UUID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /**
     * Detects the pattern WHERE field = <scalar> (single equality, no AND/OR)
     * and returns a KeyValue so the caller can build an OData key-path URL
     * (/EntitySet('uuid') or /EntitySet(42)) instead of a ?$filter parameter.
     *
     * Recognised scalar types:
     *   UUID string  →  quoted  e.g. WHERE id = '08EC1E80-...'
     *   Integer      →  unquoted  e.g. WHERE id = 42
     *
     * Returns null for anything more complex: compound conditions, non-scalar
     * values, or an empty WHERE clause.
     *
     * @param Condition[] $where
     * @param string[]    $aliases Table aliases to strip (e.g. ['t0'])
     * @param string|null $pkField When provided, only promote to key-path if the
     *                             WHERE field exactly matches this name. Passing
     *                             the known primary-key field name makes detection
     *                             precise rather than heuristic.
     */
    public static function extract(array $where, array $aliases = [], ?string $pkField = null): ?KeyValue
    {
        // Must be exactly one non-operator condition with no AND/OR
        $conditions = array_values(array_filter($where, static fn($c) => !$c->isOperator));

        if (count($conditions) !== 1) {
            return null;
        }

        $expr = $conditions[0]->expr;

        // Strip table alias prefix (e.g. "t0.") before matching
        if (!empty($aliases)) {
            $pattern = '/\b(' . implode('|', array_map('preg_quote', $aliases)) . ')\./i';
            $expr    = preg_replace($pattern, '', $expr);
        }

        // Extract the field name from "field = value" so we can validate it
        // against $pkField before checking the value type.
        if (!preg_match('/^(\w+)\s*=\s*(.+?)\s*$/', $expr, $parts)) {
            return null;
        }

        $field = $parts[1];
        $value = trim($parts[2]);

        if ($pkField !== null && $field !== $pkField) {
            return null;
        }

        // UUID string: WHERE field = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
        if (preg_match("/^'(" . self::UUID_PATTERN . ")'\s*$/i", $value, $m)) {
            return new KeyValue(value: $m[1], quoted: true);
        }

        // Integer: WHERE field = 42
        if (preg_match('/^(\d+)$/', $value, $m)) {
            return new KeyValue(value: $m[1], quoted: false);
        }

        return null;
    }
}
