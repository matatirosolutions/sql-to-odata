<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata;

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

        return implode(' ', $parts);
    }

    private static function convertCondition(string $expr): string
    {
        foreach (self::OPERATOR_MAP as $sql => $odata) {
            if (str_contains($expr, $sql)) {
                [$left, $right] = explode($sql, $expr, 2);
                return trim($left) . ' ' . $odata . ' ' . trim($right);
            }
        }

        return $expr;
    }

}
