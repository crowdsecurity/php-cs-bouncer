<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


// Parse argument

$requestedIp = $argv[1];
$bouncerKey = $argv[2];
if (!$requestedIp || !$bouncerKey) {
    die('Usage: php check-ip.php <IP> <BOUNCER_KEY>');
}
// Instantiate the Stream logger
$logger = new Logger('example');

// Display logs with DEBUG verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler(__DIR__ . '/crowdsec.log', 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Init
$configs = [
    'api_key' => $bouncerKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache',
];
$bouncer = new Bouncer($configs, $logger);

// Ask remediation to LAPI

echo "\nVerify $requestedIp...\n";
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"




