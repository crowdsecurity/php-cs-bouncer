<?php

declare(strict_types=1);

use CrowdSecBouncer\Constants;
use CrowdSecBouncer\RestClient;
use Psr\Log\LoggerInterface;

class WatcherClient
{
    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    /** @var LoggerInterface */
    private $logger;

    /** @var RestClient */
    private $watcherClient;

    /** @var array<string> */
    private $baseHeaders = null;

    /** @var string */
    private $token;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Configure this instance.
     */
    public function configure(): void
    {
        // Create Watcher Client.
        /** @var string */
        $apiUrl = getenv('LAPI_URL');
        $this->baseHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => Constants::BASE_USER_AGENT,
        ];
        $this->watcherClient = new RestClient($this->logger);
        $this->watcherClient->configure($apiUrl, $this->baseHeaders, 2);
        $this->logger->info('Watcher client initialized');
    }

    /** Set the initial watcher state */
    public function setInitialState(): void
    {
        $this->logger->info('Set initial state');
        $this->deleteAllDecisions();
        $this->addBaseDecisions();
    }

    /** Set the initial watcher state */
    public function setSecondState(): void
    {
        $this->logger->info('Set second state');
        $this->deleteAllDecisions();
        $this->addNewDecisions();
    }

    /**
     * Ensure we retrieved a JWT to connect the API.
     */
    private function ensureLogin(): void
    {
        if (!$this->token) {
            $data = [
                'machine_id' => self::WATCHER_LOGIN,
                'password' => self::WATCHER_PASSWORD,
            ];
            /** @var array */
            $credentials = $this->watcherClient->request('/v1/watchers/login', null, $data, 'POST');
            $this->token = $credentials['token'];
            $this->baseHeaders['Authorization'] = 'Bearer '.$this->token;
        }
    }

    /**
     * Request the Watcher API.
     */
    private function request(string $endpoint, array $queryParams = null, array $bodyParams = null, string $method = 'GET', array $headers = null, int $timeout = null): ?array
    {
        $this->ensureLogin();

        return $this->watcherClient->request($endpoint, $queryParams, $bodyParams, $method, $headers ?: $this->baseHeaders, $timeout);
    }

    private function deleteAllDecisions(): void
    {
        // Delete all existing decisions.
        $this->logger->info('Delete all decisions');
        $this->request('/v1/decisions', null, null, 'DELETE');
    }

    private function addBaseDecisions(): void
    {
        $now = new DateTime();
        $stopAt12h = (clone $now)->modify('+12 hours')->format('Y-m-d\TH:i:s.000\Z');
        $stopAt24h = (clone $now)->modify('+24 hours')->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $ipCaptcha12h = [
            'capacity' => 0,
            'decisions' => [
              [
                'duration' => '12h',
                'origin' => 'cscli',
                'scenario' => 'captcha single IP '.TestHelpers::BAD_IP.' for 12h for PHPUnit tests',
                'scope' => 'Ip',
                'type' => 'captcha',
                'value' => TestHelpers::BAD_IP,
              ],
            ],
            'events' => [
            ],
            'events_count' => 1,
            'labels' => null,
            'leakspeed' => '0',
            'message' => 'setup for PHPUnit tests',
            'scenario' => 'setup for PHPUnit tests',
            'scenario_hash' => '',
            'scenario_version' => '',
            'simulated' => false,
            'source' => [
              'scope' => 'Ip',
              'value' => TestHelpers::BAD_IP,
            ],
            'start_at' => $startAt,
            'stop_at' => $stopAt12h,
          ];
        $result = $this->request('/v1/alerts', null, [$ipCaptcha12h], 'POST');
        $this->logger->info('Decision '.$result[0].' added: '.$ipCaptcha12h['decisions'][0]['scenario'].'');

        $rangeBan24h = [
            'capacity' => 0,
            'decisions' => [
              [
                'duration' => '24h',
                'origin' => 'cscli',
                'scenario' => 'ban range '.TestHelpers::BAD_IP.'/'.TestHelpers::IP_RANGE.' for 24h for PHPUnit tests',
                'scope' => 'Range',
                'type' => 'ban',
                'value' => TestHelpers::BAD_IP.'/'.TestHelpers::IP_RANGE,
              ],
            ],
            'events' => [
            ],
            'events_count' => 1,
            'labels' => null,
            'leakspeed' => '0',
            'message' => 'setup for PHPUnit tests',
            'scenario' => 'setup for PHPUnit tests',
            'scenario_hash' => '',
            'scenario_version' => '',
            'simulated' => false,
            'source' => [
              'scope' => 'Range',
              'value' => TestHelpers::BAD_IP.'/'.TestHelpers::IP_RANGE,
            ],
            'start_at' => $startAt,
            'stop_at' => $stopAt24h,
          ];
        $result = $this->request('/v1/alerts', null, [$rangeBan24h], 'POST');
        $this->logger->info('Decision '.$result[0].' added: '.$rangeBan24h['decisions'][0]['scenario'].'');
    }

    /**
     * Add new decisions (captcha TestHelpers::NEWLY_BAD_IP for 36h + ban TestHelpers::NEWLY_BAD_IP/TestHelpers::IP_RANGE for 48h).
     */
    public function addNewDecisions(): void
    {
        $now = new DateTime();
        $stopAt48h = (clone $now)->modify('+48 hours')->format('Y-m-d\TH:i:s.000\Z');
        $stopAt36h = (clone $now)->modify('+36 hours')->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $ipBan36h = [
            'capacity' => 0,
            'decisions' => [
              0 => [
                'duration' => '36h',
                'origin' => 'cscli',
                'scenario' => 'ban single IP '.TestHelpers::NEWLY_BAD_IP.' for 36h for PHPUnit tests',
                'scope' => 'Ip',
                'type' => 'ban',
                'value' => TestHelpers::NEWLY_BAD_IP,
              ],
            ],
            'events' => [
            ],
            'events_count' => 1,
            'labels' => null,
            'leakspeed' => '0',
            'message' => 'updated state for PHPUnit tests',
            'scenario' => 'updated state for PHPUnit tests',
            'scenario_hash' => '',
            'scenario_version' => '',
            'simulated' => false,
            'source' => [
              'scope' => 'Ip',
              'value' => TestHelpers::NEWLY_BAD_IP,
            ],
            'start_at' => $startAt,
            'stop_at' => $stopAt36h,
          ];
        $result = $this->request('/v1/alerts', null, [$ipBan36h], 'POST');
        $this->logger->info('Decision '.$result[0].' added: '.$ipBan36h['decisions'][0]['scenario'].'');

        $rangeCaptcha48h = [
            'capacity' => 0,
            'decisions' => [
              0 => [
                'duration' => '48h',
                'origin' => 'cscli',
                'scenario' => 'captcha range '.TestHelpers::NEWLY_BAD_IP.'/'.TestHelpers::IP_RANGE.' for 48h for PHPUnit tests',
                'scope' => 'Range',
                'type' => 'captcha',
                'value' => TestHelpers::NEWLY_BAD_IP.'/'.TestHelpers::IP_RANGE,
              ],
            ],
            'events' => [
            ],
            'events_count' => 1,
            'labels' => null,
            'leakspeed' => '0',
            'message' => 'setup for PHPUnit tests',
            'scenario' => 'setup for PHPUnit tests',
            'scenario_hash' => '',
            'scenario_version' => '',
            'simulated' => false,
            'source' => [
              'scope' => 'Range',
              'value' => TestHelpers::NEWLY_BAD_IP.'/'.TestHelpers::IP_RANGE,
            ],
            'start_at' => $startAt,
            'stop_at' => $stopAt48h,
          ];
        $result = $this->request('/v1/alerts', null, [$rangeCaptcha48h], 'POST');
        $this->logger->info('Decision '.$result[0].' added: '.$rangeCaptcha48h['decisions'][0]['scenario']);
    }
}
