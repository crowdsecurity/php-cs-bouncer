<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Parse arguments
$bouncerApiKey = $argv[1]; // required
$apiUrl = $argv[3] ?: 'http://crowdsec:8080';

if (!$bouncerApiKey) {
    echo 'Usage: php clear-cache.php <api_key>';
    exit(1);
}
echo "\nClear the cache...\n";

// Configure paths
$logPath = __DIR__.'/.crowdsec.log';
$cachePath = __DIR__ . '/.cache';

// Instantiate the "PhpFilesAdapter" cache adapter
$cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter('', 0, $cachePath);
// Or Redis: $cacheAdapter = new RedisAdapter(RedisAdapter::createConnection('redis://your-redis-host:6379'));
// Or Memcached: $cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://your-memcached-host:11211'));

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
