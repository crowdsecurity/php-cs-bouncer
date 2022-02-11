<?php

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../settings.php';

use CrowdSecBouncer\StandAloneBounce;

$bounce = new StandAloneBounce();
$bounce->setDebug($crowdSecStandaloneBouncerConfig['debug_mode']??false);
$bounce->setDisplayErrors($crowdSecStandaloneBouncerConfig['display_errors'] ?? false);
$bounce->init($crowdSecStandaloneBouncerConfig);
$bouncer = $bounce->getBouncerInstance();
$bouncer->refreshBlocklistCache();
echo 'OK';
