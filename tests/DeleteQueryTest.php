<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\DeleteQuery;
use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class DeleteQueryTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    public function testReturnsDeleteQuery(): void
    {
        $result = $this->converter->parse('DELETE FROM Users WHERE Id = 1');
        $this->assertInstanceOf(DeleteQuery::class, $result);
    }

    public function testEntitySet(): void
    {
        $result = $this->converter->parse('DELETE FROM Users WHERE Id = 1');
        $this->assertSame('Users', $result->entitySet);
    }

    public function testIntegerWhereProducesFilter(): void
    {
        // Library produces $filter; driver rewrites to key-path when it knows the PK.
        $result = $this->converter->parse('DELETE FROM Users WHERE Id = 1');
        $this->assertSame('Id eq 1', $result->filter);
    }

    public function testCompoundFilter(): void
    {
        $result = $this->converter->parse("DELETE FROM Users WHERE Status = 'Inactive' AND Age > 65");
        $this->assertSame("Status eq 'Inactive' and Age gt 65", $result->filter);
    }

    public function testMissingWhereThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('DELETE without a WHERE clause is not supported.');
        $this->converter->parse('DELETE FROM Users');
    }
}
