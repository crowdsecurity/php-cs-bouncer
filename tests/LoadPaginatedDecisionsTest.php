<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/*
TODO P3
cf https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/slice
*/

final class LoadPaginatedDecisionsTest extends TestCase
{
    public function testCanLoad10FirstDecisions(): void
    {
        //...(0, 10)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testCanLoad10LastDecisions(): void
    {
        //...(-10)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testCanLoad5FirstDecisions(): void
    {
        //...(-10, 0)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testCanLoadAllDecisions(): void
    {
        //...(0)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testCanLoadTheSecondDecision(): void
    {
        //...(0, 2)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }
}