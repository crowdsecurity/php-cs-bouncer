<?php
/**
 * This script is aimed to be called by an auto-prepend directive
 * @see docs/USER_GUIDE.md
 */
require_once __DIR__ . '../../../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use CrowdSecBouncer\StandAloneBounce;

$bounce = new StandAloneBounce();

$bounce->init($crowdSecStandaloneBouncerConfig);
$bounce->safelyBounce();
