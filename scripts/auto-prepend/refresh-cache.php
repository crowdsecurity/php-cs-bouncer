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

use CrowdSecBouncer\StandaloneBouncer;

$bouncer = new StandaloneBouncer($crowdSecStandaloneBouncerConfig);
$bouncer->refreshBlocklistCache();
echo 'Cache has been refreshed (if stream mode is enabled)' . \PHP_EOL;
