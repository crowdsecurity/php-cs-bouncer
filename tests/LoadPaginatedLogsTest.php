<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class LoadPaginatedLogsTest extends TestCase
{
    /**
     * @group integration
     */
    public function testCanLoad10FirstLogInputs(): void
    {
        // ...(0, 10)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group integration
     */
    public function testCanLoad10LastLogInputs(): void
    {
        // ...(-10)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group integration
     */
    public function testCanLoad5FirstLogInputs(): void
    {
        // ...(-10, 0)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group integration
     */
    public function testCanLoadAllLogInputs(): void
    {
        // ...(0)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group integration
     */
    public function testCanLoadTheSecondLogInput(): void
    {
        // ...(0, 2)
        $this->markTestIncomplete('This test has not been implemented yet.');
    }
}
