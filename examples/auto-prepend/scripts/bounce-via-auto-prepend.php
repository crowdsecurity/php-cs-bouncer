<?php

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../settings.php';

use CrowdSecBouncer\StandAloneBounce;

$bounce = new StandAloneBounce();
$bounce->init($crowdSecStandaloneBouncerConfig);
$bounce->safelyBounce();
