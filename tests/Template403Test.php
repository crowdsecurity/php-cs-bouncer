<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use CrowdSecBouncer\Bouncer;

final class Template403Test extends TestCase
{
    /**
     * @group integration
     * @covers Bouncer
     */
    public function testCanGetAccessForbiddenHtmlTemplate(): void
    {
        // TODO P2 update the 403 tests
        //$bouncer = new Bouncer();
        //$bouncer->configure($config, $cacheAdapter);
        //$this->assertIsString($bouncer->getAccessForbiddenHtmlTemplate());
    }
}
