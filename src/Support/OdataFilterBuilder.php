<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use PhpMyAdmin\SqlParser\Components\Condition;

class OdataFilterBuilder
{
    private const array OPERATOR_MAP = [
        '!=' => 'ne',
        '<>' => 'ne',
        '>=' => 'ge',
        '<=' => 'le',
        '>'  => 'gt',
        '<'  => 'lt',
        '='  => 'eq',
    ];

    /**
     * Given the index of an opening quote in $str, returns the index
     * immediately after the matching closing quote.
     */
    private static function skipQuotedString(string $str, int $i): int
    {
        $quote = $str[$i++];
        $len   = strlen($str);

        while ($i < $len) {
            if ($str[$i] === '\\') {
                $i += 2;
            } elseif ($str[$i] === $quote) {
                if ($quote === "'" && isset($str[$i + 1]) && $str[$i + 1] === "'") {
                    $i += 2; // SQL-style escaped quote: ''
                } else {
                    $i++;
                    break;
                }
            } else {
                $i++;
            }
        }

        return $i;
    }

    /** @return string[] */
    private static function splitInValues(string $list): array
    {
        $values  = [];
        $current = '';
        $len     = strlen($list);
        $i       = 0;

        while ($i < $len) {
            $ch = $list[$i];
            if ($ch === "'" || $ch === '"') {
                $end     = self::skipQuotedString($list, $i);
                $current .= substr($list, $i, $end - $i);
                $i       = $end;
            } elseif ($ch === ',') {
                $values[] = trim($current);
                $current  = '';
                $i++;
            } else {
                $current .= $ch;
                $i++;
            }
        }

        if (trim($current) !== '') {
            $values[] = trim($current);
        }

        return $values;
    }

    /** @param Condition[] $conditions */
    public static function build(array $conditions): string
    {
        $parts = [];

        foreach ($conditions as $condition) {
            if ($condition->isOperator) {
                $parts[] = strtolower($condition->expr);
            } else {
                $parts[] = self::convertCondition($condition->expr);
            }
        }

        return self::applyBooleanPrecedence($parts);
    }

    /**
     * Wraps AND-connected groups in parentheses when OR is also present,
     * preserving SQL precedence (AND binds tighter than OR).
     *
     * @param string[] $parts Alternating expressions and 'and'/'or' operators.
     */
    private static function applyBooleanPrecedence(array $parts): string
    {
        $operators = array_filter($parts, fn($p) => $p === 'and' || $p === 'or');

        $hasAnd = in_array('and', $operators, true);
        $hasOr  = in_array('or',  $operators, true);

        if (!$hasAnd || !$hasOr) {
            return implode(' ', $parts);
        }

        // Split on 'or', collect AND-connected segments, parenthesise multi-expression ones
        $orSegments  = [];
        $currentSegment = [];

        foreach ($parts as $part) {
            if ($part === 'or') {
                $orSegments[]   = $currentSegment;
                $currentSegment = [];
            } else {
                $currentSegment[] = $part;
            }
        }
        $orSegments[] = $currentSegment;

        $clauses = array_map(function (array $segment): string {
            $joined = implode(' ', $segment);
            // More than one expression in this segment means it contains ANDs
            $expressionCount = (count($segment) + 1) / 2;
            return $expressionCount > 1 ? "($joined)" : $joined;
        }, $orSegments);

        return implode(' or ', $clauses);
    }

    private const string IDENTIFIER_PATTERN = '/^[`"](.+)[`"]$/s';

    private const string GUID_PATTERN     = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const string DATE_PATTERN     = '/^\d{4}-\d{2}-\d{2}$/';
    private const string DATETIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?$/';

    private static function unquoteIdentifier(string $name): string
    {
        return preg_match(self::IDENTIFIER_PATTERN, trim($name), $m) ? $m[1] : trim($name);
    }

    /**
     * Unquotes date, datetime, and GUID literals for OData v4 filter syntax.
     * Plain strings remain quoted; numeric/null literals are returned as-is.
     */
    private static function formatFilterValue(string $value): string
    {
        if ((str_starts_with($value, "'") && str_ends_with($value, "'"))
            || (str_starts_with($value, '"') && str_ends_with($value, '"'))
        ) {
            $inner = substr($value, 1, -1);

            if (preg_match(self::GUID_PATTERN, $inner)
                || preg_match(self::DATE_PATTERN, $inner)
                || preg_match(self::DATETIME_PATTERN, $inner)
            ) {
                return $inner;
            }
        }

        return $value;
    }

    private static function convertCondition(string $expr): string
    {
        if (preg_match('/^(.+?)\s+IS\s+NOT\s+NULL$/i', $expr, $m)) {
            return self::unquoteIdentifier($m[1]) . ' ne null';
        }

        if (preg_match('/^(.+?)\s+IS\s+NULL$/i', $expr, $m)) {
            return self::unquoteIdentifier($m[1]) . ' eq null';
        }

        if (preg_match("/^(.+?)\s+LIKE\s+'([^']*)'\s*$/i", $expr, $m)) {
            $col     = self::unquoteIdentifier($m[1]);
            $pattern = $m[2];

            $leadingPct  = str_starts_with($pattern, '%');
            $trailingPct = str_ends_with($pattern, '%');
            $value       = trim($pattern, '%');

            if (str_contains($value, '%') || str_contains($value, '_')) {
                throw new ConversionException(
                    "Unsupported LIKE pattern '$pattern': interior wildcards (% and _) have no OData equivalent."
                );
            }

            if ($leadingPct && $trailingPct) {
                return "contains($col, '$value')";
            }
            if ($trailingPct) {
                return "startswith($col, '$value')";
            }
            if ($leadingPct) {
                return "endswith($col, '$value')";
            }

            return "$col eq '$value'";
        }

        if (preg_match('/^(.+?)\s+IN\s*\((.+)\)\s*$/i', $expr, $m)) {
            $col    = self::unquoteIdentifier($m[1]);
            $values  = array_map(fn($v) => self::formatFilterValue(trim($v)), self::splitInValues($m[2]));
            $clauses = array_map(fn($v) => "$col eq $v", $values);
            return '(' . implode(' or ', $clauses) . ')';
        }

        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            if ($expr[$i] === "'" || $expr[$i] === '"') {
                $i = self::skipQuotedString($expr, $i);
                continue;
            }

            // Try to match each operator at this unquoted position
            foreach (self::OPERATOR_MAP as $sql => $odata) {
                if (substr($expr, $i, strlen($sql)) === $sql) {
                    $left  = self::unquoteIdentifier(substr($expr, 0, $i));
                    $right = self::formatFilterValue(trim(substr($expr, $i + strlen($sql))));
                    return $left . ' ' . $odata . ' ' . $right;
                }
            }

            $i++;
        }

        return $expr;
    }

}
