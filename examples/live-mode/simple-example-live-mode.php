<?php

require __DIR__ . '/../../vendor/autoload.php';

use CrowdSecBouncer\Bouncer;

// Parse arguments
$bouncerApiKey = $argv[1]; // required
$requestedIp = $argv[2]; // required
$apiUrl = $argv[3] ?: 'http://127.0.0.1:8080';
if (!$bouncerApiKey || !$requestedIp) {
    echo 'Usage: php full-example-live-mode.php <api_key> <requested_ip> [<api_url>]';
    exit(1);
}

// Init bouncer
$bouncer = new Bouncer();
$bouncer->configure(['api_key' => $bouncerApiKey, 'api_url' => $apiUrl]);

// Ask remediation to LAPI
echo "\nVerify $requestedIp with $apiUrl...\n";
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"
