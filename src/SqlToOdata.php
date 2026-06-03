<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Parser\InsertParser;
use Matatirosoln\SqlToOdata\Parser\SelectParser;
use Matatirosoln\SqlToOdata\Parser\UpdateParser;
use Matatirosoln\SqlToOdata\Query\Query;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;

class SqlToOdata
{
    public function parse(string $sql): Query
    {
        $parser = new Parser($sql);
        if (!empty($parser->errors)) {
            throw new ConversionException('SQL parse error: ' . $parser->errors[0]->getMessage());
        }

        $statement = $parser->statements[0] ?? null;

        return match (true) {
            $statement instanceof SelectStatement => new SelectParser()->parse($statement),
            $statement instanceof InsertStatement => new InsertParser()->parse($statement),
            $statement instanceof UpdateStatement => new UpdateParser()->parse($statement),
            default => throw new ConversionException('Only SELECT, INSERT, and UPDATE statements are supported.'),
        };
    }

    public function convert(string $sql): string
    {
        $query = $this->parse($sql);

        if (!$query instanceof SelectQuery) {
            throw new ConversionException('convert() only supports SELECT statements. Use parse() for INSERT and UPDATE.');
        }

        return $query->queryString;
    }
}
