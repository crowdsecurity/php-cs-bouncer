<?php
/**
 * This script is aimed to be called by an auto-prepend directive
 * @see docs/USER_GUIDE.md
 * @var $crowdSecStandaloneBouncerConfig
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use CrowdSecBouncer\StandaloneBounce;

$bounce = new StandaloneBounce();
// Retro compatibility with crowdsec php lib < 0.14.0
if(isset($crowdSecStandaloneBouncerConfig['bouncing_level']) && $crowdSecStandaloneBouncerConfig['bouncing_level'] === 'normal_boucing'){
    $crowdSecStandaloneBouncerConfig['bouncing_level'] = 'normal_bouncing';
}elseif($crowdSecStandaloneBouncerConfig['bouncing_level'] === 'flex_boucing'){
    $crowdSecStandaloneBouncerConfig['bouncing_level'] = 'flex_bouncing';
}

$bouncer = $bounce->init($crowdSecStandaloneBouncerConfig);
$bouncer->refreshBlocklistCache();
echo 'Cache has been refreshed'.PHP_EOL;