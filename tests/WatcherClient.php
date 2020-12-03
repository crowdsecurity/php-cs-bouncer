<?php

declare(strict_types=1);

use CrowdSecBouncer\RestClient;
use CrowdSecBouncer\Constants;

class WatcherClient
{
    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    public static function setCrowdSecContext(): void
    {
        // Create Watcher Client
        /** @var string */
        $apiUrl = getenv('LAPI_URL');
        $baseHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => Constants::BASE_USER_AGENT
        ];
        $watcherClient = new RestClient();
        $watcherClient->configure($apiUrl, $baseHeaders, 2);

        // Get JWT
        $data = [
            'machine_id' => self::WATCHER_LOGIN,
            'password' => self::WATCHER_PASSWORD
        ];
        /** @var array */
        $credentials = $watcherClient->request('/v1/watchers/login', null, $data, 'POST');
        $token = $credentials['token'];

        $baseHeaders['Authorization'] = 'Bearer '.$token;

        // Delete all existing decisions
        $watcherClient->request('/v1/decisions', null, null, 'DELETE', $baseHeaders);

        // Add fixtures decisions
        /** @var string */
        $jsonString = file_get_contents(__DIR__.'/data/alert_sample.json');
        $data = json_decode($jsonString, true);

        $now = new DateTime();
        $stopAt = (clone $now)->modify('+1 day')->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $ipCaptcha12h = $data[0];
        $ipCaptcha12h['start_at'] = $startAt;
        $ipCaptcha12h['stop_at'] = $stopAt;
        $watcherClient->request('/v1/alerts', null, [$ipCaptcha12h], 'POST', $baseHeaders);

        $rangeBan24h = $data[1];
        $rangeBan24h['start_at'] = $startAt;
        $rangeBan24h['stop_at'] = $stopAt;
        $watcherClient->request('/v1/alerts', null, [$rangeBan24h], 'POST', $baseHeaders);
    }
}
