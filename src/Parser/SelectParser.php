<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Parser;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

class SelectParser
{
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
            && strtoupper($statement->expr[0]->function ?? '') === 'COUNT';
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
