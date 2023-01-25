<?php
/**
 * This script is aimed to be called by an auto-prepend directive.
 *
 * @see docs/USER_GUIDE.md
 *
 * @var $crowdSecStandaloneBouncerConfig
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\StandaloneBouncer;

// If there is any technical problem while bouncing, don't block the user.
try {
    $bouncer = new StandaloneBouncer($crowdSecStandaloneBouncerConfig);
    $bouncer->run();
} catch (\Throwable $e) {
    $displayErrors = $crowdSecStandaloneBouncerConfig['display_errors'] ?? false;
    if (true === $displayErrors) {
        throw new BouncerException($e->getMessage(), $e->getCode(), $e);
    }
}