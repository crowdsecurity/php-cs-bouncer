<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CrowdSecBouncer\StandaloneBouncer;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


// Parse argument

$requestedIp = $argv[1]??null;
$bouncerKey = $argv[2]??null;
if (!$requestedIp || !$bouncerKey) {
    exit('Usage: php standalone-check-ip-live.php <IP> <BOUNCER_KEY>');
}
// Instantiate the Stream logger
$logger = new Logger('example');

// Display logs with DEBUG verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Init
$configs = [
    'api_key' => $bouncerKey,
    'api_url' => 'https://crowdsec:8080',
    'fs_cache_path' => __DIR__ . '/.cache',
    'stream_mode' => false
];
$bouncer = new StandaloneBouncer($configs, $logger);

// Ask remediation to LAPI

echo "\nVerify $requestedIp...\n";
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"




