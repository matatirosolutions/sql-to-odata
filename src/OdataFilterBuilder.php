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
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            // Skip over quoted string literals, handling '' and \' escapes
            if ($expr[$i] === "'" || $expr[$i] === '"') {
                $quote = $expr[$i++];
                while ($i < $len) {
                    if ($expr[$i] === '\\') {
                        $i += 2;
                    } elseif ($expr[$i] === $quote) {
                        if ($quote === "'" && isset($expr[$i + 1]) && $expr[$i + 1] === "'") {
                            $i += 2; // SQL-style escaped quote: ''
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $i++;
                    }
                }
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
