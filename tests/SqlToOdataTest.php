<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Exception\ConversionException;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use Matatirosoln\SqlToOdata\SqlToOdata;
use PHPUnit\Framework\TestCase;

class SqlToOdataTest extends TestCase
{
    /** Default converter — OData v4 spec-compliant (GUIDs unquoted). */
    private SqlToOdata $converter;

    /** quoteGuids: true — for servers like FileMaker that need quoted GUIDs. */
    private SqlToOdata $converterQuoteGuids;

    protected function setUp(): void
    {
        $this->converter           = new SqlToOdata();
        $this->converterQuoteGuids = new SqlToOdata(quoteGuids: true);
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
        $sql    = "SELECT Id, Name FROM Users WHERE Status = 'Active' ORDER BY Name ASC LIMIT 5";
        $result = $this->converter->parse($sql);
        $this->assertStringContainsString('$select=Id,Name', $result->queryString);
        $this->assertStringContainsString('$filter=', $result->queryString);
        $this->assertStringContainsString('$orderby=Name asc', $result->queryString);
        $this->assertStringContainsString('$top=5', $result->queryString);
    }

    public function testCountQuery(): void
    {
        $result = $this->converter->parse('SELECT COUNT(*) FROM Users');
        $this->assertSame('Users', $result->entitySet);
        $this->assertSame('/$count', $result->queryString);
    }

    public function testCountQueryWithWhere(): void
    {
        $result = $this->converter->parse("SELECT COUNT(*) FROM Users WHERE Status = 'Active'");
        $this->assertSame('/$count?$filter=Status eq \'Active\'', $result->queryString);
    }

    public function testSchemaQualifiedTableName(): void
    {
        $result = $this->converter->parse('SELECT * FROM dbo.Users');
        $this->assertSame('Users', $result->entitySet);
    }

    public function testSelectDistinctThrowsException(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('SELECT DISTINCT is not supported');
        $this->converter->parse('SELECT DISTINCT Name FROM Users');
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

    // Doctrine-generated SQL: aliased tables (e.g. "FROM User t0")

    public function testDoctrineAliasedTableIsResolvedAsEntitySet(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0"
        );
        $this->assertInstanceOf(SelectQuery::class, $result);
        $this->assertSame('User', $result->entitySet);
    }

    public function testDoctrineAliasedTableSelectColumns(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0"
        );
        $this->assertSame('?$select=id,Name,City', $result->queryString);
    }

    public function testDoctrineAliasedTableWhereStripsAlias(): void
    {
        // The table alias prefix (t0.) must be stripped from WHERE expressions.
        // UUID goes into $filter as a bare Edm.Guid literal (OData v4 default).
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0 " .
            "WHERE t0.id = '08EC1E80-89DB-4513-8E3D-9D33D6BA006C'"
        );
        $this->assertSame(
            '?$select=id,Name,City&$filter=id eq 08EC1E80-89DB-4513-8E3D-9D33D6BA006C',
            $result->queryString,
        );
    }

    public function testDoctrineAliasedTableWhereStripsAliasQuoteGuids(): void
    {
        // With quoteGuids: true the UUID stays quoted — required by FileMaker.
        $result = $this->converterQuoteGuids->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0 " .
            "WHERE t0.id = '08EC1E80-89DB-4513-8E3D-9D33D6BA006C'"
        );
        $this->assertSame(
            '?$select=id,Name,City&$filter=id eq \'08EC1E80-89DB-4513-8E3D-9D33D6BA006C\'',
            $result->queryString,
        );
    }

    public function testDoctrineAliasedTableWhereWithStringValue(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2 FROM User t0 WHERE t0.Name = 'Alice'"
        );
        $this->assertStringContainsString('$filter=Name eq \'Alice\'', $result->queryString);
    }

    public function testDoctrineAliasedTableWhereCompoundCondition(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2 FROM User t0 " .
            "WHERE t0.Name = 'Alice' AND t0.City = 'Auckland'"
        );
        $this->assertStringContainsString("Name eq 'Alice'", $result->queryString);
        $this->assertStringContainsString("City eq 'Auckland'", $result->queryString);
        $this->assertStringNotContainsString('t0.', $result->queryString);
    }

    // quoteGuids option

    public function testGuidUnquotedByDefault(): void
    {
        $uuid   = '11111111-2222-3333-4444-555555555555';
        $result = $this->converter->parse("SELECT * FROM Users WHERE OtherGuid = '$uuid'");
        $this->assertStringContainsString("OtherGuid eq $uuid", $result->queryString);
        $this->assertStringNotContainsString("'$uuid'", $result->queryString);
    }

    public function testGuidQuotedWhenConfigured(): void
    {
        $uuid   = '11111111-2222-3333-4444-555555555555';
        $result = $this->converterQuoteGuids->parse("SELECT * FROM Users WHERE OtherGuid = '$uuid'");
        $this->assertStringContainsString("OtherGuid eq '$uuid'", $result->queryString);
    }

    // Column metadata — field/alias pairs for result mapping

    public function testColumnMetadataExtractedForAliasedColumns(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0"
        );
        $this->assertSame(
            [
                ['field' => 'id',   'alias' => 'id_1'],
                ['field' => 'Name', 'alias' => 'Name_2'],
                ['field' => 'City', 'alias' => 'City_3'],
            ],
            $result->columns,
        );
    }

    public function testColumnMetadataPreservesSelectOrder(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.City AS City_3, t0.Name AS Name_2, t0.id AS id_1 FROM User t0"
        );
        $this->assertSame('City', $result->columns[0]['field']);
        $this->assertSame('Name', $result->columns[1]['field']);
        $this->assertSame('id',   $result->columns[2]['field']);
    }

    public function testColumnMetadataEmptyForSelectStar(): void
    {
        $result = $this->converter->parse('SELECT * FROM Users');
        $this->assertSame([], $result->columns);
    }

    public function testColumnMetadataEmptyForCountQuery(): void
    {
        $result = $this->converter->parse('SELECT COUNT(*) FROM Users');
        $this->assertSame([], $result->columns);
    }

    public function testColumnMetadataUsesColumnNameWhenNoAlias(): void
    {
        $result = $this->converter->parse('SELECT id, Name FROM Users');
        $this->assertSame([
            ['field' => 'id',   'alias' => 'id'],
            ['field' => 'Name', 'alias' => 'Name'],
        ], $result->columns);
    }

    public function testColumnMetadataWithDoctrineWhereAndLimit(): void
    {
        $result = $this->converter->parse(
            "SELECT t0.id AS id_1, t0.Name AS Name_2, t0.City AS City_3 FROM User t0 " .
            "WHERE t0.Name = 'Alice' LIMIT 1"
        );
        $this->assertSame(
            [
                ['field' => 'id',   'alias' => 'id_1'],
                ['field' => 'Name', 'alias' => 'Name_2'],
                ['field' => 'City', 'alias' => 'City_3'],
            ],
            $result->columns,
        );
    }
}
