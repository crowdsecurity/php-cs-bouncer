<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSecBouncer\Geolocation;

if (isset($_GET['ip'])) {
    if (\PHP_SESSION_NONE === session_status()) {
        session_name('crowdsec-geoloc');
        session_start();
    }
    $requestedIp = $_GET['ip'];
    $dbName = $_GET['db-name'] ?? 'GeoLite2-Country.mmdb';
    $dbType = $_GET['db-type'] ?? 'country';
    $saveInSession = isset($_GET['session-save']);

    $geolocConfig = [
        'enabled' => true,
        'save_in_session' => $saveInSession,
        'type' => 'maxmind',
        'maxmind' => [
            'database_type' => $dbType,
            'database_path' => '/var/www/html/my-own-modules/crowdsec-php-lib/tests/' . $dbName
        ]
    ];

    $geolocation = new Geolocation();
    if(!$saveInSession){
        $geolocation->clearGeolocationSessionContext();
    }

    $countryResult = $geolocation->getCountryResult($geolocConfig, $requestedIp);
    $country = $countryResult['country'];
    $notFound = $countryResult['not_found'];
    $error = $countryResult['error'];
    $sessionMessage = $saveInSession ? 'true' : 'false';

    echo "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'/>
    <title>Geolocation for IP: $requestedIp</title>
</head>

<body>
    <h1>For IP $requestedIp:</h1>
    <ul>
        <li>Country: $country</li>
        <li>Not Found message: $notFound</li>
        <li>Error message: $error</li>
        <li>Session save: $sessionMessage</li>
    </ul>
</body>
</html>
";
} else {
    die('You must pass an "ip" param' . PHP_EOL);
}




