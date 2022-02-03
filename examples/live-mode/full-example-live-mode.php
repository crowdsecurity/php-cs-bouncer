<?php

require __DIR__.'/../../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Parse arguments
$bouncerApiKey = $argv[1]; // required
$requestedIp = $argv[2]; // required
$apiUrl = $argv[3] ?: 'http://127.0.0.1:8080';

if (!$bouncerApiKey || !$requestedIp) {
    echo 'Usage: php full-example-live-mode.php <api_key> <requested_ip> [<api_url>]';
    exit(1);
}
echo "\nVerify $requestedIp with $apiUrl...\n";

// Configure paths
$logPath = __DIR__.'/../.crowdsec.log';
$cachePath = __DIR__.'/../.cache';

// Instanciate the "PhpFilesAdapter" cache adapter
$cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter('', 0, $cachePath);
// Or Redis: $cacheAdapter = new RedisAdapter(RedisAdapter::createConnection('redis://your-redis-host:6379'));
// Or Memcached: $cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://your-memcached-host:11211'));

// Instanciate the Stream logger with info level(optional)
$logger = new Logger('example');

// Display logs with INFO verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler($logPath, 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instanciate the bouncer
$bouncer = new Bouncer($cacheAdapter, $logger);
$bouncer->configure([
    'api_key' => $bouncerApiKey,
    'api_url' => $apiUrl,
    'api_user_agent' => 'MyCMS CrowdSec Bouncer/1.0.0',
    'api_timeout' => 1,
    'live_mode' => true,
    'max_remediation_level' => 'ban',
    'cache_expiration_for_clean_ip' => 2,
    'cache_expiration_for_bad_ip' => 30,
]);

// Ask remediation to LAPI
$remediation = $bouncer->getRemediationForIp($requestedIp);

// "ban", "captcha" or "bypass"
echo "\nResult: $remediation\n\n";
