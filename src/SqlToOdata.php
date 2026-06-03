<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

class SqlToOdata
{
    public function parse(string $sql): ParsedQuery
    {
        $parser = new Parser($sql);
        if (!empty($parser->errors)) {
            throw new ConversionException('SQL parse error: ' . $parser->errors[0]->getMessage());
        }

        $statement = $parser->statements[0] ?? null;

        if (!$statement instanceof SelectStatement) {
            throw new ConversionException('Only SELECT statements are supported.');
        }

        $this->rejectSubqueries($statement);

        $table = $statement->from[0]->table ?? null;
        if ($table === null || $table === '') {
            throw new ConversionException('Could not determine entity set from SQL.');
        }

        return new ParsedQuery(
            entitySet: trim($table, '`"\''),
            queryString: $this->buildOdataQuery($statement),
        );
    }

    public function convert(string $sql): string
    {
        return $this->parse($sql)->queryString;
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

    private function buildOdataQuery(SelectStatement $statement): string
    {
        $params = [];

        // $select
        if (!empty($statement->expr)) {
            $columns = array_map(fn($expr) => $expr->column ?? '*', $statement->expr);
            $columns = array_filter($columns, fn($col) => $col !== '*');
            if (!empty($columns)) {
                $params[] = '$select=' . implode(',', $columns);
            }
        }

        // $filter (WHERE)
        if ($statement->where !== null) {
            $params[] = '$filter=' . OdataFilterBuilder::build($statement->where);
        }

        // $orderby
        if (!empty($statement->order)) {
            $orders = array_map(function ($order) {
                return $order->expr->column . ' ' . strtolower($order->type ?? 'asc');
            }, $statement->order);
            $params[] = '$orderby=' . implode(',', $orders);
        }

        // $top (LIMIT)
        if ($statement->limit !== null) {
            $params[] = '$top=' . $statement->limit->rowCount;

            if ($statement->limit->offset) {
                $params[] = '$skip=' . $statement->limit->offset;
            }
        }

        return '?' . implode('&', $params);
    }
}
