<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

// Configure paths
$logPath = __DIR__ . '/.crowdsec.log';

$bouncerKey = $argv[1];
if (!$bouncerKey) {
    die('Usage: php refresh-cache.php <BOUNCER_KEY>');
}

// Instantiate the Stream logger with warning level
$logger = new Logger('example');
$fileHandler = new RotatingFileHandler(__DIR__ . '/crowdsec.log', 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instantiate the bouncer
$configs = [
    'api_key' => $bouncerKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache'
];
$bouncer = new Bouncer($configs, $logger);

// Refresh the blocklist cache
$bouncer->refreshBlocklistCache();
echo "Cache successfully refreshed.\n";
