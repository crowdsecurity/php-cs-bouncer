<?php
/**
 * This script is aimed to be called directly in a browser
 * It will act on the LAPI cache depending on the auto-prepend settings file and on the passed parameter.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../auto-prepend/settings.php';

use CrowdSecBouncer\StandaloneBouncer;
/**
 * @var $crowdSecStandaloneBouncerConfig
 */
if (isset($_GET['action']) && in_array($_GET['action'], ['refresh', 'clear', 'prune'])) {
    $action = $_GET['action'];
    $bouncer = new StandaloneBouncer($crowdSecStandaloneBouncerConfig);

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
    exit('You must pass an "action" param (refresh, clear or prune)' . \PHP_EOL);
}
