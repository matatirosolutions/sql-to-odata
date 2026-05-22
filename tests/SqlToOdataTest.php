<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
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
        $result = $this->converter->convert('SELECT * FROM Users');
        $this->assertSame('?', $result);
    }

    public function testSelectSpecificColumns(): void
    {
        $result = $this->converter->convert('SELECT Id, Name, Email FROM Users');
        $this->assertSame('?$select=Id,Name,Email', $result);
    }

    public function testWhereClause(): void
    {
        $result = $this->converter->convert("SELECT * FROM Users WHERE Status = 'Active'");
        $this->assertStringContainsString('$filter=', $result);
        $this->assertStringContainsString('eq', $result);
    }

    public function testOrderBy(): void
    {
        $result = $this->converter->convert('SELECT * FROM Users ORDER BY Name ASC');
        $this->assertStringContainsString('$orderby=Name asc', $result);
    }

    public function testLimit(): void
    {
        $result = $this->converter->convert('SELECT * FROM Users LIMIT 10');
        $this->assertStringContainsString('$top=10', $result);
    }

    public function testLimitWithOffset(): void
    {
        $result = $this->converter->convert('SELECT * FROM Users LIMIT 10 OFFSET 20');
        $this->assertStringContainsString('$top=10', $result);
        $this->assertStringContainsString('$skip=20', $result);
    }

    public function testCombinedQuery(): void
    {
        $sql = "SELECT Id, Name FROM Users WHERE Status = 'Active' ORDER BY Name ASC LIMIT 5";
        $result = $this->converter->convert($sql);
        $this->assertStringContainsString('$select=Id,Name', $result);
        $this->assertStringContainsString('$filter=', $result);
        $this->assertStringContainsString('$orderby=Name asc', $result);
        $this->assertStringContainsString('$top=5', $result);
    }

    public function testInvalidSqlThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->converter->convert('NOT VALID SQL !!!');
    }

    public function testNonSelectThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->converter->convert('DELETE FROM Users');
    }
}
