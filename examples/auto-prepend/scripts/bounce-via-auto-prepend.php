<?php

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../settings.php';

use CrowdSecBouncer\StandAloneBounce;

$bounce = new StandAloneBounce();
$bounce->setDebug($crowdSecStandaloneBouncerConfig['debug_mode']);
$bounce->setDisplayErrors($crowdSecStandaloneBouncerConfig['display_errors']);
$bounce->init($crowdSecStandaloneBouncerConfig);
$bounce->safelyBounce();
