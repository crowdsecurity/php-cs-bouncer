<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;




// Parse arguments
$bouncerApiKey = $argv[1]; // required
$apiUrl = $argv[3] ?: 'https://crowdsec:8080';

if (!$bouncerApiKey) {
    echo 'Usage: php clear-cache.php <api_key>';
    exit(1);
}
echo "\nClear the cache...\n";

// Configure paths
$logPath = __DIR__.'/.crowdsec.log';

// Instantiate the Stream logger
$logger = new Logger('example');

// Display logs with DEBUG verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler($logPath, 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instantiate the bouncer
$configs = [
    'api_key' => $bouncerApiKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache',
];
$bouncer = new Bouncer($configs, $logger);

// Clear the cache.
$bouncer->clearCache();
echo "Cache successfully cleared.\n";
