<?php

declare(strict_types=1);

use CrowdSecBouncer\Bouncer;
use PHPUnit\Framework\TestCase;

final class Template403Test extends TestCase
{
    /**
     * @group integration
     * @covers \Bouncer
     */
    public function testCanGetAccessForbiddenHtmlTemplate(): void
    {
        // TODO P2 update the 403 tests
        //$bouncer = new Bouncer($cacheAdapter);
        //$bouncer->configure($config);
        //$this->assertIsString($bouncer->getAccessForbiddenHtmlTemplate());
    }
}
