<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\StandaloneBouncer;

/**
 * The class that apply a bounce for Unit Test without sending response
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
class StandaloneBouncerNoResponse extends StandaloneBouncer
{

    protected function sendResponse(string $body, int $statusCode): void
    {
        // Just do nothing to avoid PHP UNIT header already sent or exit error
    }

    protected function redirectResponse(string $redirect): void
    {
        // Just do nothing to avoid PHP UNIT header already sent or exit error
    }
}
