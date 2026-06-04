<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Parser\DeleteParser;
use Matatirosoln\SqlToOdata\Parser\InsertParser;
use Matatirosoln\SqlToOdata\Parser\SelectParser;
use Matatirosoln\SqlToOdata\Parser\UpdateParser;
use Matatirosoln\SqlToOdata\Query\Query;
use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;

class SqlToOdata
{
    private readonly SelectParser $selectParser;
    private readonly InsertParser $insertParser;
    private readonly UpdateParser $updateParser;
    private readonly DeleteParser $deleteParser;

    /**
     * @param bool $quoteGuids
     *   Pass true for OData servers (e.g. FileMaker) that cannot handle bare
     *   Edm.Guid literals in $filter expressions. Defaults to false (OData v4
     *   spec-compliant: GUIDs are emitted as unquoted typed literals).
     */
    public function __construct(bool $quoteGuids = false)
    {
        $filterBuilder       = new OdataFilterBuilder($quoteGuids);
        $this->selectParser  = new SelectParser($filterBuilder);
        $this->insertParser  = new InsertParser();
        $this->updateParser  = new UpdateParser($filterBuilder);
        $this->deleteParser  = new DeleteParser($filterBuilder);
    }

    public function parse(string $sql): Query
    {
        $parser = new Parser($sql);
        if (!empty($parser->errors)) {
            throw new ConversionException('SQL parse error: ' . $parser->errors[0]->getMessage());
        }

        $statement = $parser->statements[0] ?? null;

        return match (true) {
            $statement instanceof SelectStatement => $this->selectParser->parse($statement),
            $statement instanceof InsertStatement => $this->insertParser->parse($statement),
            $statement instanceof UpdateStatement => $this->updateParser->parse($statement),
            $statement instanceof DeleteStatement => $this->deleteParser->parse($statement),
            default => throw new ConversionException('Only SELECT, INSERT, UPDATE, and DELETE statements are supported.'),
        };
    }
}
