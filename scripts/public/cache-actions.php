<?php
/**
 * This script is aimed to be called directly in a browser
 * It will act on the LAPI cache depending on the auto-prepend settings file and on the passed parameter
 *
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../auto-prepend/settings.php';

use CrowdSecBouncer\StandaloneBounce;

if (isset($_GET['action']) && in_array($_GET['action'],['refresh', 'clear', 'prune', 'warm-up'])) {
    $action = $_GET['action'];
    $bounce = new StandaloneBounce();
    /** @var $crowdSecStandaloneBouncerConfig */
    $bouncer = $bounce->init($crowdSecStandaloneBouncerConfig);
    switch ($action) {
        case 'refresh':
            $bouncer->refreshBlocklistCache();
            break;
        case 'clear':
            $bouncer->clearCache();
            break;
        case 'prune':
            $bouncer->pruneCache();
            break;
        case 'warm-up':
            $bouncer->warmBlocklistCacheUp();
            break;
        default:
            throw new Exception("Unknown cache action type:$action");
    }

    echo "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'/>
    <title>Cache action: $action</title>
</head>

<body>
    <h1>Cache action has been done: $action</h1>
</body>
</html>
";
} else {
    die('You must pass an "action" param (refresh or clear)' . PHP_EOL);
}




