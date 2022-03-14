<?php

require_once __DIR__ . '../../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

// Configure paths
$logPath = __DIR__.'/.crowdsec.log';
$cachePath = __DIR__ . '/.cache';

// Instantiate the "PhpFilesAdapter" cache adapter
$cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter('', 0, $cachePath);
// Or Redis: $cacheAdapter = new RedisAdapter(RedisAdapter::createConnection('redis://your-redis-host:6379'));
// Or Memcached: $cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://your-memcached-host:11211'));

// Parse argument

$bouncerKey = $argv[1];
if (!$bouncerKey) {
    die('Usage: php refresh-cache.php <BOUNCER_KEY>');
}

// Instantiate the Stream logger with info level(optional)
$logger = new Logger('example');
$fileHandler = new RotatingFileHandler(__DIR__.'/crowdsec.log', 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instantiate the bouncer
$bouncer = new Bouncer($cacheAdapter, $logger);
$bouncer->configure([
        'api_key' => $bouncerKey,
        'api_url' => 'http://crowdsec:8080'
    ]
);

// Refresh the blocklist cache
$bouncer->refreshBlocklistCache();
echo "Cache successfully refreshed.\n";