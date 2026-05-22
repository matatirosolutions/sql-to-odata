<?php

declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Tests;

use Matatirosoln\SqlToOdata\OdataFilterBuilder;
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
}
