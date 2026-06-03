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

    public function testSingleRowStringValues(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Email) VALUES ('John', 'john@example.com')");
        $this->assertSame([['Name' => 'John', 'Email' => 'john@example.com']], $result->rows);
    }

    public function testSingleRowIntegerValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Age) VALUES ('John', 30)");
        $this->assertSame([['Name' => 'John', 'Age' => 30]], $result->rows);
    }

    public function testSingleRowFloatValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Products (Name, Price) VALUES ('Widget', 9.99)");
        $this->assertSame([['Name' => 'Widget', 'Price' => 9.99]], $result->rows);
    }

    public function testSingleRowNullValue(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, DeletedAt) VALUES ('John', NULL)");
        $this->assertSame([['Name' => 'John', 'DeletedAt' => null]], $result->rows);
    }

    public function testMultipleRows(): void
    {
        $result = $this->converter->parse("INSERT INTO Users (Name, Age) VALUES ('John', 30), ('Jane', 25), ('Bob', 40)");
        $this->assertCount(3, $result->rows);
        $this->assertSame(['Name' => 'John', 'Age' => 30], $result->rows[0]);
        $this->assertSame(['Name' => 'Jane', 'Age' => 25], $result->rows[1]);
        $this->assertSame(['Name' => 'Bob',  'Age' => 40], $result->rows[2]);
    }

    public function testInsertWithoutColumnListThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('INSERT without a column list is not supported.');
        $this->converter->parse("INSERT INTO Users VALUES ('John', 30)");
    }
}
