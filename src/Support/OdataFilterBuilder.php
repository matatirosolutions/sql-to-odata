<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

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

    private static function convertCondition(string $expr): string
    {
        if (preg_match('/^(.+?)\s+IS\s+NOT\s+NULL$/i', $expr, $m)) {
            return trim($m[1]) . ' ne null';
        }

        if (preg_match('/^(.+?)\s+IS\s+NULL$/i', $expr, $m)) {
            return trim($m[1]) . ' eq null';
        }

        if (preg_match("/^(.+?)\s+LIKE\s+'([^']*)'\s*$/i", $expr, $m)) {
            $col     = trim($m[1]);
            $pattern = $m[2];

            $leadingPct  = str_starts_with($pattern, '%');
            $trailingPct = str_ends_with($pattern, '%');
            $value       = trim($pattern, '%');

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
            $col    = trim($m[1]);
            $values = array_map('trim', self::splitInValues($m[2]));
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
                    $left = trim(substr($expr, 0, $i));
                    $right = trim(substr($expr, $i + strlen($sql)));
                    return $left . ' ' . $odata . ' ' . $right;
                }
            }

            $i++;
        }

        return $expr;
    }

}
