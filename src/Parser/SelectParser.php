<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Support\OdataExpandBuilder;
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

    public function __construct(
        private readonly OdataFilterBuilder $filterBuilder
    ) { }

    public function parse(SelectStatement $statement): SelectQuery
    {
        if (in_array('DISTINCT', $statement->options->options ?? [], true)) {
            throw new ConversionException('SELECT DISTINCT is not supported; OData has no equivalent.');
        }

        $this->rejectSubqueries($statement);

        $from  = $statement->from[0];
        // When a table alias is present the SQL parser puts the real name in
        // ->expr and leaves ->table null; fall back accordingly.
        $table = ($from->table !== null && $from->table !== '')
            ? $from->table
            : ($from->expr ?? null);

        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        // Collect all table aliases so we can strip them from WHERE expressions.
        $aliases = [];
        foreach ($statement->from as $fromItem) {
            if ($fromItem->alias !== null && $fromItem->alias !== '') {
                $aliases[] = $fromItem->alias;
            }
        }

        return new SelectQuery(
            entitySet: trim($table, '`"\''),
            queryString: $this->buildQueryString($statement, $aliases),
            columns: $this->extractColumns($statement),
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
        $col       = $m[1] ?? $expr->expr;
        $odataFunc = self::AGGREGATE_MAP[$func] ?? strtolower($func);
        $alias     = $alias ?? ucfirst(strtolower($func)) . ucfirst($col);

        return "$col with $odataFunc as $alias";
    }

    private function buildApplyString(SelectStatement $statement): string
    {
        $aggregates     = array_filter($statement->expr, fn($e) => $e->function !== null);
        $aggregateParts = array_map(fn($e) => $this->buildAggregateClause($e), $aggregates);
        $aggregateStr   = implode(',', $aggregateParts);

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

    /** @param string[] $aliases Table aliases to strip from WHERE/ORDER expressions. */
    private function buildQueryString(SelectStatement $statement, array $aliases = []): string
    {
        if ($this->isCountQuery($statement)) {
            $params = [];
            if ($statement->where !== null) {
                $params[] = '$filter=' . $this->filterBuilder->build($this->stripAliases($statement->where, $aliases));
            }
            return '/$count' . (empty($params) ? '' : '?' . implode('&', $params));
        }

        if ($this->isAggregateQuery($statement)) {
            $params = [];
            if ($statement->where !== null) {
                $params[] = '$filter=' . $this->filterBuilder->build($this->stripAliases($statement->where, $aliases));
            }
            $params[] = $this->buildApplyString($statement);
            return '?' . implode('&', $params);
        }

        $params = [];

        // Collect joined table names/aliases so we can route their columns to $expand
        $joinedTables = [];
        foreach ($statement->join ?? [] as $join) {
            $joinedTables[] = $join->expr->table;
            if ($join->expr->alias !== null) {
                $joinedTables[] = $join->expr->alias;
            }
        }

        // $select — exclude columns that belong to a joined table
        if (!empty($statement->expr)) {
            $columns = [];
            foreach ($statement->expr as $expr) {
                $qualifier = $expr->table ?? '';
                if ($qualifier !== '' && in_array($qualifier, $joinedTables, true)) {
                    continue;
                }
                $col = $expr->column ?? null;
                if ($col !== null && $col !== '*') {
                    $columns[] = $col;
                }
            }
            if (!empty($columns)) {
                $encoded  = array_map(static fn(string $c) => str_replace('~', '%7E', $c), $columns);
                $params[] = '$select=' . implode(',', $encoded);
            }
        }

        // $expand (JOIN)
        if (!empty($statement->join)) {
            $params[] = '$expand=' . OdataExpandBuilder::build($statement->join, $statement->expr);
        }

        if ($statement->where !== null) {
            $params[] = '$filter=' . $this->filterBuilder->build($this->stripAliases($statement->where, $aliases));
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

    /**
     * Extracts an ordered column map from the SELECT expressions.
     *
     * Returns a list of {field, alias} pairs — one per concrete column —
     * in the same order they appear in the SELECT clause. This lets the DBAL
     * result layer return values in the correct positional order and with the
     * SQL alias names that Doctrine (or other consumers) expect.
     *
     * @return list<array{field: string, alias: string}>
     */
    private function extractColumns(SelectStatement $statement): array
    {
        if ($this->isCountQuery($statement) || $this->isAggregateQuery($statement)) {
            return [];
        }

        $columns = [];

        foreach ($statement->expr as $expr) {
            $field = $expr->column ?? null;

            if ($field === null || $field === '' || $field === '*' || $expr->function !== null) {
                continue;
            }

            $alias     = ($expr->alias !== null && $expr->alias !== '') ? $expr->alias : $field;
            $columns[] = ['field' => trim($field, '`"\''), 'alias' => $alias];
        }

        return $columns;
    }

    /**
     * Strips table alias prefixes (e.g. "t0.") from WHERE condition expressions.
     *
     * @param  \PhpMyAdmin\SqlParser\Components\Condition[] $conditions
     * @return \PhpMyAdmin\SqlParser\Components\Condition[]
     */
    private function stripAliases(array $conditions, array $aliases): array
    {
        if (empty($aliases)) {
            return $conditions;
        }

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $aliases)) . ')\./i';

        return array_map(function ($condition) use ($pattern) {
            $clone       = clone $condition;
            $clone->expr = preg_replace($pattern, '', $clone->expr);
            return $clone;
        }, $conditions);
    }
}
