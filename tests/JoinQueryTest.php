<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class JoinQueryTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    public function testSimpleJoinProducesExpand(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users JOIN Orders ON Users.Id = Orders.UserId');
        $this->assertStringContainsString('$expand=Orders', $result->queryString);
    }

    public function testJoinWithColumnsFromBothTables(): void
    {
        $sql    = 'SELECT Users.Id, Users.Name, Orders.OrderDate FROM Users JOIN Orders ON Users.Id = Orders.UserId';
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$select=Id,Name', $result->queryString);
        $this->assertStringContainsString('$expand=Orders($select=OrderDate)', $result->queryString);
    }

    public function testJoinWithNoSelectedColumnsFromJoinedTable(): void
    {
        $sql    = 'SELECT Users.Id, Users.Name FROM Users JOIN Orders ON Users.Id = Orders.UserId';
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$select=Id,Name', $result->queryString);
        $this->assertStringContainsString('$expand=Orders', $result->queryString);
        $this->assertStringNotContainsString('$expand=Orders($select=', $result->queryString);
    }

    public function testMultipleJoins(): void
    {
        $sql    = 'SELECT * FROM Users JOIN Orders ON Users.Id = Orders.UserId JOIN Addresses ON Users.Id = Addresses.UserId';
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$expand=Orders,Addresses', $result->queryString);
    }

    public function testLeftJoin(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users LEFT JOIN Orders ON Users.Id = Orders.UserId');
        $this->assertStringContainsString('$expand=Orders', $result->queryString);
    }

    public function testJoinWithWhereClause(): void
    {
        $sql    = "SELECT * FROM Users JOIN Orders ON Users.Id = Orders.UserId WHERE Users.Status = 'Active'";
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$expand=Orders', $result->queryString);
        $this->assertStringContainsString('$filter=', $result->queryString);
    }

    public function testJoinWithOrderAndLimit(): void
    {
        $sql    = 'SELECT Users.Id, Orders.Total FROM Users JOIN Orders ON Users.Id = Orders.UserId ORDER BY Orders.Total DESC LIMIT 10';
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$select=Id', $result->queryString);
        $this->assertStringContainsString('$expand=Orders($select=Total)', $result->queryString);
        $this->assertStringContainsString('$top=10', $result->queryString);
    }

    public function testJoinEntitySetIsMainTable(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users JOIN Orders ON Users.Id = Orders.UserId');
        $this->assertSame('Users', $result->entitySet);
    }
}
