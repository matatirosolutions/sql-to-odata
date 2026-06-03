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

    public function testSimpleFilter(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Name = 'John' WHERE Id = 1");
        $this->assertSame('Id eq 1', $result->filter);
    }

    public function testCompoundFilter(): void
    {
        $result = $this->converter->parse("UPDATE Users SET Age = 30 WHERE Status = 'Active' AND Role = 'admin'");
        $this->assertSame("Status eq 'Active' and Role eq 'admin'", $result->filter);
    }

    public function testMissingWhereThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('UPDATE without a WHERE clause is not supported.');
        $this->converter->parse("UPDATE Users SET Name = 'John'");
    }
}
