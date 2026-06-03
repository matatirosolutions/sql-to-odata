<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class SqlToOdataTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    public function testSelectAllColumns(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users');
        $this->assertInstanceOf(SelectQuery::class, $result);
        $this->assertSame('Users', $result->entitySet);
        $this->assertSame('?', $result->queryString);
    }

    public function testSelectSpecificColumns(): void
    {
        $result = $this->converter->parse('SELECT Id, Name, Email FROM Users');
        $this->assertSame('?$select=Id,Name,Email', $result->queryString);
    }

    public function testWhereClause(): void
    {
        $result = $this->converter->parse("SELECT * FROM Users WHERE Status = 'Active'");
        $this->assertStringContainsString('$filter=', $result->queryString);
        $this->assertStringContainsString('eq', $result->queryString);
    }

    public function testOrderBy(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users ORDER BY Name ASC');
        $this->assertStringContainsString('$orderby=Name asc', $result->queryString);
    }

    public function testLimit(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users LIMIT 10');
        $this->assertStringContainsString('$top=10', $result->queryString);
    }

    public function testLimitWithOffset(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users LIMIT 10 OFFSET 20');
        $this->assertStringContainsString('$top=10', $result->queryString);
        $this->assertStringContainsString('$skip=20', $result->queryString);
    }

    public function testCombinedQuery(): void
    {
        $sql = "SELECT Id, Name FROM Users WHERE Status = 'Active' ORDER BY Name ASC LIMIT 5";
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$select=Id,Name', $result->queryString);
        $this->assertStringContainsString('$filter=', $result->queryString);
        $this->assertStringContainsString('$orderby=Name asc', $result->queryString);
        $this->assertStringContainsString('$top=5', $result->queryString);
    }

    public function testSubqueryInWhereThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Subqueries in WHERE are not supported.');
        $this->converter->parse('SELECT * FROM Users WHERE Id IN (SELECT UserId FROM Orders)');
    }

    public function testSubqueryInFromThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Subqueries in FROM are not supported.');
        $this->converter->parse('SELECT * FROM (SELECT * FROM Users) AS sub');
    }

    public function testSubqueryInSelectExpressionThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Subqueries in SELECT expressions are not supported.');
        $this->converter->parse('SELECT (SELECT COUNT(*) FROM Orders) AS total FROM Users');
    }

    public function testInvalidSqlThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->converter->parse('NOT VALID SQL !!!');
    }

    public function testUnsupportedStatementThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Only SELECT, INSERT, UPDATE, and DELETE statements are supported.');
        $this->converter->parse('CREATE TABLE Users (Id INT)');
    }
}
