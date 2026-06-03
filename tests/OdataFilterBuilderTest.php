<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\Support\OdataFilterBuilder;
use PhpMyAdmin\SqlParser\Components\Condition;
use PHPUnit\Framework\TestCase;

class OdataFilterBuilderTest extends TestCase
{
    private function makeCondition(string $expr, bool $isOperator = false): Condition
    {
        $condition = new Condition($expr);
        $condition->isOperator = $isOperator;
        return $condition;
    }

    public function testEqualsOperator(): void
    {
        $conditions = [$this->makeCondition("Status = 'Active'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("Status eq 'Active'", $result);
    }

    public function testNotEqualsOperator(): void
    {
        $conditions = [$this->makeCondition('Age != 18')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('Age ne 18', $result);
    }

    public function testGreaterThanOperator(): void
    {
        $conditions = [$this->makeCondition('Age > 18')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('Age gt 18', $result);
    }

    public function testLessThanOrEqualOperator(): void
    {
        $conditions = [$this->makeCondition('Age <= 65')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('Age le 65', $result);
    }

    public function testOperatorInsideSingleQuotedValue(): void
    {
        // Old str_contains approach would match >= inside the string value
        $conditions = [$this->makeCondition("Tag = 'price>=0'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("Tag eq 'price>=0'", $result);
    }

    public function testOperatorInsideDoubleQuotedValue(): void
    {
        $conditions = [$this->makeCondition('Tag = "a<b"')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('Tag eq "a<b"', $result);
    }

    public function testSqlEscapedQuoteInValue(): void
    {
        // SQL-style escaped single quote: '' inside a string literal
        $conditions = [$this->makeCondition("Name = 'O''Brien'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("Name eq 'O''Brien'", $result);
    }

    public function testAndOperator(): void
    {
        $conditions = [
            $this->makeCondition("Status = 'Active'"),
            $this->makeCondition('and', true),
            $this->makeCondition('Age > 18'),
        ];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("Status eq 'Active' and Age gt 18", $result);
    }

    // Date, datetime, and GUID filter values

    public function testDateValueIsUnquoted(): void
    {
        $conditions = [$this->makeCondition("CreatedAt = '2024-01-15'")];
        $this->assertSame('CreatedAt eq 2024-01-15', OdataFilterBuilder::build($conditions));
    }

    public function testDatetimeValueIsUnquoted(): void
    {
        $conditions = [$this->makeCondition("CreatedAt = '2024-01-15T09:30:00Z'")];
        $this->assertSame('CreatedAt eq 2024-01-15T09:30:00Z', OdataFilterBuilder::build($conditions));
    }

    public function testDatetimeWithOffsetIsUnquoted(): void
    {
        $conditions = [$this->makeCondition("CreatedAt = '2024-01-15T09:30:00+12:00'")];
        $this->assertSame('CreatedAt eq 2024-01-15T09:30:00+12:00', OdataFilterBuilder::build($conditions));
    }

    public function testGuidValueIsUnquoted(): void
    {
        $conditions = [$this->makeCondition("Id = '12345678-1234-1234-1234-123456789abc'")];
        $this->assertSame('Id eq 12345678-1234-1234-1234-123456789abc', OdataFilterBuilder::build($conditions));
    }

    public function testPlainStringValueRemainsQuoted(): void
    {
        $conditions = [$this->makeCondition("Status = 'Active'")];
        $this->assertSame("Status eq 'Active'", OdataFilterBuilder::build($conditions));
    }

    public function testGuidInInList(): void
    {
        $a = '11111111-1111-1111-1111-111111111111';
        $b = '22222222-2222-2222-2222-222222222222';
        $conditions = [$this->makeCondition("Id IN ('$a', '$b')")];
        $this->assertSame("(Id eq $a or Id eq $b)", OdataFilterBuilder::build($conditions));
    }

    // AND/OR precedence

    public function testPureAndNeedsNoParens(): void
    {
        $conditions = [
            $this->makeCondition('a = 1'),
            $this->makeCondition('and', true),
            $this->makeCondition('b = 2'),
            $this->makeCondition('and', true),
            $this->makeCondition('c = 3'),
        ];
        $this->assertSame('a eq 1 and b eq 2 and c eq 3', OdataFilterBuilder::build($conditions));
    }

    public function testPureOrNeedsNoParens(): void
    {
        $conditions = [
            $this->makeCondition('a = 1'),
            $this->makeCondition('or', true),
            $this->makeCondition('b = 2'),
            $this->makeCondition('or', true),
            $this->makeCondition('c = 3'),
        ];
        $this->assertSame('a eq 1 or b eq 2 or c eq 3', OdataFilterBuilder::build($conditions));
    }

    public function testAndGroupWrappedWhenOrPresent(): void
    {
        // SQL: a = 1 OR b = 2 AND c = 3  →  AND binds tighter
        $conditions = [
            $this->makeCondition('a = 1'),
            $this->makeCondition('or', true),
            $this->makeCondition('b = 2'),
            $this->makeCondition('and', true),
            $this->makeCondition('c = 3'),
        ];
        $this->assertSame('a eq 1 or (b eq 2 and c eq 3)', OdataFilterBuilder::build($conditions));
    }

    public function testMultipleAndGroupsSeparatedByOr(): void
    {
        // SQL: a = 1 AND b = 2 OR c = 3 AND d = 4
        $conditions = [
            $this->makeCondition('a = 1'),
            $this->makeCondition('and', true),
            $this->makeCondition('b = 2'),
            $this->makeCondition('or', true),
            $this->makeCondition('c = 3'),
            $this->makeCondition('and', true),
            $this->makeCondition('d = 4'),
        ];
        $this->assertSame('(a eq 1 and b eq 2) or (c eq 3 and d eq 4)', OdataFilterBuilder::build($conditions));
    }

    // IS NULL / IS NOT NULL

    public function testIsNull(): void
    {
        $conditions = [$this->makeCondition('DeletedAt IS NULL')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('DeletedAt eq null', $result);
    }

    public function testIsNotNull(): void
    {
        $conditions = [$this->makeCondition('DeletedAt IS NOT NULL')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('DeletedAt ne null', $result);
    }

    // LIKE

    public function testLikeContains(): void
    {
        $conditions = [$this->makeCondition("Name LIKE '%foo%'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("contains(Name, 'foo')", $result);
    }

    public function testLikeStartsWith(): void
    {
        $conditions = [$this->makeCondition("Name LIKE 'foo%'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("startswith(Name, 'foo')", $result);
    }

    public function testLikeEndsWith(): void
    {
        $conditions = [$this->makeCondition("Name LIKE '%foo'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("endswith(Name, 'foo')", $result);
    }

    public function testLikeExactMatch(): void
    {
        $conditions = [$this->makeCondition("Name LIKE 'foo'")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("Name eq 'foo'", $result);
    }

    // IN

    public function testInWithStrings(): void
    {
        $conditions = [$this->makeCondition("Status IN ('Active', 'Pending')")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("(Status eq 'Active' or Status eq 'Pending')", $result);
    }

    public function testInWithIntegers(): void
    {
        $conditions = [$this->makeCondition('Age IN (18, 21, 65)')];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame('(Age eq 18 or Age eq 21 or Age eq 65)', $result);
    }

    public function testInWithCommaInsideValue(): void
    {
        // Comma inside a quoted string must not split the value
        $conditions = [$this->makeCondition("Tag IN ('a,b', 'c')")];
        $result = OdataFilterBuilder::build($conditions);
        $this->assertSame("(Tag eq 'a,b' or Tag eq 'c')", $result);
    }
}
