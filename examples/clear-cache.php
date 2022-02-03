<?php

require __DIR__.'/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Parse arguments
$bouncerApiKey = $argv[1]; // required
$apiUrl = $argv[3] ?: 'http://127.0.0.1:8080';

if (!$bouncerApiKey) {
    echo 'Usage: php clear-cache.php <api_key>';
    exit(1);
}
echo "\nClear the cache...\n";

// Configure paths
$logPath = __DIR__.'/.crowdsec.log';
$cachePath = __DIR__.'/.cache';

// Instantiate a the "PhpFilesAdapter" cache adapter
// Note: to select another cache adapter (Memcached, Redis, ...), try other examples.
$cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter('', 0, $cachePath);

// Instantiate the Stream logger with info level(optional)
$logger = new Logger('example');

// Display logs with INFO verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler($logPath, 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instantiate the bouncer
$bouncer = new Bouncer($cacheAdapter, $logger);
$bouncer->configure(['api_key' => $bouncerApiKey, 'api_url' => $apiUrl]);

// Clear the cache.
$bouncer->clearCache();
echo "Cache successfully cleared.\n";
