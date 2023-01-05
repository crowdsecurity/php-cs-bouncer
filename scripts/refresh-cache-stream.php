<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\StandaloneBouncer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;


$bouncerKey = $argv[1]??null;
if (!$bouncerKey) {
    exit('Usage: php refresh-cache-stream.php <BOUNCER_KEY>');
}

// Instantiate the Stream logger
$logger = new Logger('example');

// Display logs with DEBUG verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Instantiate the bouncer
$configs = [
    'api_key' => $bouncerKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache',
    'stream_mode' => true,
];
$bouncer = new StandaloneBouncer($configs, $logger);

// Refresh the blocklist cache
$bouncer->refreshBlocklistCache();
echo "Cache successfully refreshed.\n";
