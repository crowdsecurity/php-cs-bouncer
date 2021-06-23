<?php

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../settings.php';

use CrowdSecBouncer\StandAloneBounce;

$bounce = new StandAloneBounce();
$bounce->init($crowdSecStandaloneBouncerConfig);
$bounce->setDebug($crowdSecStandaloneBouncerConfig['debug_mode']);
$bounce->safelyBounce();
