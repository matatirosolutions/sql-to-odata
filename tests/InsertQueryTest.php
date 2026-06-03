<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\InsertQuery;
use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class InsertQueryTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    public function testReturnsInsertQuery(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Email) VALUES ('John', 'john@example.com')");
        $this->assertInstanceOf(InsertQuery::class, $result);
    }

    public function testEntitySet(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name) VALUES ('John')");
        $this->assertSame('Users', $result->entitySet);
    }

    public function testStringValues(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Email) VALUES ('John', 'john@example.com')");
        $this->assertSame(['Name' => 'John', 'Email' => 'john@example.com'], $result->body);
    }

    public function testIntegerValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Age) VALUES ('John', 30)");
        $this->assertSame(['Name' => 'John', 'Age' => 30], $result->body);
    }

    public function testFloatValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Products (Name, Price) VALUES ('Widget', 9.99)");
        $this->assertSame(['Name' => 'Widget', 'Price' => 9.99], $result->body);
    }

    public function testNullValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, DeletedAt) VALUES ('John', NULL)");
        $this->assertSame(['Name' => 'John', 'DeletedAt' => null], $result->body);
    }
}
