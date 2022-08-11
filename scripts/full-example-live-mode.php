<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Parse arguments
$bouncerApiKey = $argv[1]; // required
$requestedIp = $argv[2]; // required
$apiUrl = $argv[3] ?: 'https://crowdsec:8080';

if (!$bouncerApiKey || !$requestedIp) {
    echo 'Usage: php full-example-live-mode.php <api_key> <requested_ip> [<api_url>]';
    exit(1);
}
echo "\nVerify $requestedIp with $apiUrl...\n";

// Configure paths
$logPath = __DIR__.'/crowdsec.log';

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
    'api_url' => $apiUrl,
    'api_user_agent' => 'MyCMS CrowdSec Bouncer/1.0.0',
    'api_timeout' => 1,
    'stream_mode' => false,
    'max_remediation_level' => 'ban',
    'clean_ip_cache_duration' => 300,
    'bad_ip_cache_duration' => 30,
    'fs_cache_path' => __DIR__ . '/../.cache'
];
$bouncer = new Bouncer($configs, $logger);


// Ask remediation to LAPI
$remediation = $bouncer->getRemediationForIp($requestedIp);

// "ban", "captcha" or "bypass"
echo "\nResult: $remediation\n\n";
