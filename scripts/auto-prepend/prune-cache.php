<?php
/**
 * This script is aimed to be called by a cron job.
 *
 * @see docs/USER_GUIDE.md
 *
 * @var $crowdSecStandaloneBouncerConfig
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use CrowdSecBouncer\StandaloneBouncer;

$bouncer = new StandaloneBouncer($crowdSecStandaloneBouncerConfig);
$bouncer->pruneCache();
