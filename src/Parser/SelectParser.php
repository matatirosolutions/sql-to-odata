<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

class SelectParser
{
    private const array AGGREGATE_MAP = [
        'MAX' => 'max',
        'MIN' => 'min',
        'SUM' => 'sum',
        'AVG' => 'average',
    ];

    public function parse(SelectStatement $statement): SelectQuery
    {
        if (in_array('DISTINCT', $statement->options->options ?? [], true)) {
            throw new ConversionException('SELECT DISTINCT is not supported; OData has no equivalent.');
        }

        $this->rejectSubqueries($statement);

        $table = $statement->from[0]->table ?? null;
        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        return new SelectQuery(
            entitySet: trim($table, '`"\''),
            queryString: $this->buildQueryString($statement),
        );
    }

    private function isCountQuery(SelectStatement $statement): bool
    {
        return count($statement->expr) === 1
            && strtoupper($statement->expr[0]->function ?? '') === 'COUNT'
            && empty($statement->group);
    }

    private function isAggregateQuery(SelectStatement $statement): bool
    {
        if (!empty($statement->group)) {
            return true;
        }

        foreach ($statement->expr as $expr) {
            if ($expr->function !== null) {
                return true;
            }
        }

        return false;
    }

    private function buildAggregateClause(Expression $expr): string
    {
        $func  = strtoupper($expr->function ?? '');
        $alias = $expr->alias;

        if ($func === 'COUNT') {
            return '$count as ' . ($alias ?? 'count');
        }

        preg_match('/\w+\((.+)\)/i', $expr->expr ?? '', $m);
        $col      = $m[1] ?? $expr->expr;
        $odataFunc = self::AGGREGATE_MAP[$func] ?? strtolower($func);
        $alias    = $alias ?? ucfirst(strtolower($func)) . ucfirst($col);

        return "$col with $odataFunc as $alias";
    }

    private function buildApplyString(SelectStatement $statement): string
    {
        $aggregates = array_filter($statement->expr, fn($e) => $e->function !== null);
        $aggregateParts = array_map(fn($e) => $this->buildAggregateClause($e), $aggregates);

        $aggregateStr = implode(',', $aggregateParts);

        if (!empty($statement->group)) {
            $groupCols = array_map(fn($g) => $g->expr->column, $statement->group);
            $groupStr  = implode(',', $groupCols);
            return "\$apply=groupby(($groupStr),aggregate($aggregateStr))";
        }

        return "\$apply=aggregate($aggregateStr)";
    }

    private function rejectSubqueries(SelectStatement $statement): void
    {
        foreach ($statement->from as $from) {
            if ($from->subquery !== null) {
                throw new ConversionException('Subqueries in FROM are not supported.');
            }
        }

        foreach ($statement->expr as $expr) {
            if (stripos($expr->expr ?? '', 'SELECT') !== false) {
                throw new ConversionException('Subqueries in SELECT expressions are not supported.');
            }
        }

        foreach ($statement->where ?? [] as $condition) {
            if (!$condition->isOperator && stripos($condition->expr, 'SELECT') !== false) {
                throw new ConversionException('Subqueries in WHERE are not supported.');
            }
        }
    }

    private function buildQueryString(SelectStatement $statement): string
    {
        if ($this->isCountQuery($statement)) {
            $params = [];
            if ($statement->where !== null) {
                $params[] = '$filter=' . OdataFilterBuilder::build($statement->where);
            }
            return '/$count' . (empty($params) ? '' : '?' . implode('&', $params));
        }

        if ($this->isAggregateQuery($statement)) {
            $params = [];
            if ($statement->where !== null) {
                $params[] = '$filter=' . OdataFilterBuilder::build($statement->where);
            }
            $params[] = $this->buildApplyString($statement);
            return '?' . implode('&', $params);
        }

        $params = [];

        if (!empty($statement->expr)) {
            $columns = array_map(fn($expr) => $expr->column ?? '*', $statement->expr);
            $columns = array_filter($columns, fn($col) => $col !== '*');
            if (!empty($columns)) {
                $params[] = '$select=' . implode(',', $columns);
            }
        }

        if ($statement->where !== null) {
            $params[] = '$filter=' . OdataFilterBuilder::build($statement->where);
        }

        if (!empty($statement->order)) {
            $orders = array_map(
                fn($order) => $order->expr->column . ' ' . strtolower($order->type ?? 'asc'),
                $statement->order,
            );
            $params[] = '$orderby=' . implode(',', $orders);
        }

        if ($statement->limit !== null) {
            $params[] = '$top=' . $statement->limit->rowCount;

            if ($statement->limit->offset) {
                $params[] = '$skip=' . $statement->limit->offset;
            }
        }

        return '?' . implode('&', $params);
    }
}
