<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\UpdateQuery;
use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class UpdateQueryTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    public function testReturnsUpdateQuery(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = 1");
        $this->assertInstanceOf(UpdateQuery::class, $result);
    }

    public function testEntitySet(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = 1");
        $this->assertSame('Users', $result->entitySet);
    }

    public function testStringValue(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = 1");
        $this->assertSame(['Name' => 'John'], $result->body);
    }

    public function testIntegerValue(): void
    {
        $result = $this->converter->parse('UPDATE Users SET Age = 30 WHERE Id = 1');
        $this->assertSame(['Age' => 30], $result->body);
    }

    public function testMultipleSetColumns(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Name = 'John', Status = 'Active' WHERE Id = 1");
        $this->assertSame(['Name' => 'John', 'Status' => 'Active'], $result->body);
    }

    public function testIntegerWhereProducesFilter(): void
    {
        // Single integer equality: library produces $filter; driver is responsible
        // for rewriting to a key-path URL when it knows the field is the PK.
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = 1");
        $this->assertSame('Id eq 1', $result->filter);
    }

    public function testUuidWhereProducesUnquotedFilter(): void
    {
        // UUID: unquoted by default (OData v4 Edm.Guid literal).
        $uuid   = '08EC1E80-89DB-4513-8E3D-9D33D6BA006C';
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = '$uuid'");
        $this->assertSame("Id eq $uuid", $result->filter);
    }

    public function testUuidWhereProducesQuotedFilterWhenConfigured(): void
    {
        $uuid      = '08EC1E80-89DB-4513-8E3D-9D33D6BA006C';
        $converter = new SqlToOdata(quoteGuids: true);
        $result    = $converter->parse("UPDATE Users SET Name = 'John' WHERE Id = '$uuid'");
        $this->assertSame("Id eq '$uuid'", $result->filter);
    }

    public function testCompoundFilter(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Age = 30 WHERE Status = 'Active' AND Role = 'admin'");
        $this->assertSame("Status eq 'Active' and Role eq 'admin'", $result->filter);
    }

    public function testDoubleQuotedColumnNameIsStripped(): void
    {
        // Doctrine generates ANSI double-quote quoting for columns declared with
        // backtick hints, e.g. @ORM\Column(name: "`~flag`") → "~flag" in SQL.
        // The body key must be the bare field name, not the quoted identifier.
        $result = $this->converter->parse('UPDATE Items SET "~status_flag" = 0 WHERE Id = 1');
        $this->assertSame(['~status_flag' => 0], $result->body);
    }

    public function testBacktickQuotedColumnNameIsStripped(): void
    {
        $result = $this->converter->parse("UPDATE Items SET `~status_flag` = 0 WHERE Id = 1");
        $this->assertSame(['~status_flag' => 0], $result->body);
    }

    public function testMissingWhereThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('UPDATE without a WHERE clause is not supported.');
        $this->converter->parse("UPDATE Users SET Name = 'John'");
    }
}
