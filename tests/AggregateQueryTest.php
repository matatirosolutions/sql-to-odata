<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class AggregateQueryTest extends TestCase
{
    private SqlToOdata $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlToOdata();
    }

    // GROUP BY with COUNT

    public function testGroupByWithCount(): void
    {
        $result = $this->converter->parse('SELECT Status, COUNT(*) FROM Users GROUP BY Status');
        $this->assertSame('Users', $result->entitySet);
        $this->assertSame('?$apply=groupby((Status),aggregate($count as count))', $result->queryString);
    }

    public function testGroupByWithCountAndExplicitAlias(): void
    {
        $result = $this->converter->parse('SELECT Status, COUNT(*) AS total FROM Users GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate($count as total))', $result->queryString);
    }

    // GROUP BY with other aggregates

    public function testGroupByWithSum(): void
    {
        $result = $this->converter->parse('SELECT Status, SUM(Amount) FROM Orders GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate(Amount with sum as SumAmount))', $result->queryString);
    }

    public function testGroupByWithMax(): void
    {
        $result = $this->converter->parse('SELECT Status, MAX(Age) FROM Users GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate(Age with max as MaxAge))', $result->queryString);
    }

    public function testGroupByWithMin(): void
    {
        $result = $this->converter->parse('SELECT Status, MIN(Age) FROM Users GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate(Age with min as MinAge))', $result->queryString);
    }

    public function testGroupByWithAvg(): void
    {
        $result = $this->converter->parse('SELECT Status, AVG(Age) FROM Users GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate(Age with average as AvgAge))', $result->queryString);
    }

    public function testGroupByWithExplicitAlias(): void
    {
        $result = $this->converter->parse('SELECT Status, MAX(Age) AS OldestAge FROM Users GROUP BY Status');
        $this->assertSame('?$apply=groupby((Status),aggregate(Age with max as OldestAge))', $result->queryString);
    }

    // Aggregate without GROUP BY

    public function testAggregateWithoutGroupBy(): void
    {
        $result = $this->converter->parse('SELECT MAX(Age) FROM Users');
        $this->assertSame('?$apply=aggregate(Age with max as MaxAge)', $result->queryString);
    }

    public function testMultipleAggregatesWithoutGroupBy(): void
    {
        $result = $this->converter->parse('SELECT MIN(Age), MAX(Age) FROM Users');
        $this->assertSame('?$apply=aggregate(Age with min as MinAge,Age with max as MaxAge)', $result->queryString);
    }

    // WHERE + GROUP BY

    public function testGroupByWithWhere(): void
    {
        $result = $this->converter->parse("SELECT Status, COUNT(*) FROM Users WHERE Active = 1 GROUP BY Status");
        $this->assertSame("?$" . "filter=Active eq 1&\$apply=groupby((Status),aggregate(\$count as count))", $result->queryString);
    }

    // COUNT(*) alone remains /$count (not $apply)

    public function testStandaloneCountStillUsesCountEndpoint(): void
    {
        $result = $this->converter->parse('SELECT COUNT(*) FROM Users');
        $this->assertSame('/$count', $result->queryString);
    }
}
