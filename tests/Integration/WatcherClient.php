<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;


use CrowdSecBouncer\Constants;
use CrowdSecBouncer\RestClient;
use Psr\Log\LoggerInterface;

class WatcherClient
{
    public const WATCHER_LOGIN = 'PhpUnitTestMachine';
    public const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    public const HOURS24 = '+24 hours';

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
        $this->logger->info('', ['message' => 'Watcher client initialized']);
    }

    /** Set the initial watcher state */
    public function setInitialState(): void
    {
        $this->logger->info('', ['message' => 'Set initial state']);
        $this->deleteAllDecisions();
        $now = new \DateTime();
        $this->addDecision($now, '12h', '+12 hours', TestHelpers::BAD_IP, 'captcha');
        $this->addDecision($now, '24h', self::HOURS24, TestHelpers::BAD_IP.'/'.TestHelpers::IP_RANGE, 'ban');
        $this->addDecision($now, '24h', '+24 hours', TestHelpers::JAPAN, 'captcha', Constants::SCOPE_COUNTRY);
    }

    /** Set the second watcher state */
    public function setSecondState(): void
    {
        $this->logger->info('', ['message' => 'Set "second" state']);
        $this->deleteAllDecisions();
        $now = new \DateTime();
        $this->addDecision($now, '36h', '+36 hours', TestHelpers::NEWLY_BAD_IP, 'ban');
        $this->addDecision($now, '48h', '+48 hours', TestHelpers::NEWLY_BAD_IP.'/'.TestHelpers::IP_RANGE, 'captcha');
        $this->addDecision($now, '24h', self::HOURS24, TestHelpers::JAPAN, 'captcha', Constants::SCOPE_COUNTRY);
        $this->addDecision($now, '24h', self::HOURS24, TestHelpers::IP_JAPAN, 'ban');
        $this->addDecision($now, '24h', self::HOURS24, TestHelpers::IP_FRANCE, 'ban');
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

    public function deleteAllDecisions(): void
    {
        // Delete all existing decisions.
        $this->logger->info('', ['message' => 'Delete all decisions']);
        $this->request('/v1/decisions', null, null, 'DELETE');
    }

    protected function getFinalScope($scope, $value)
    {
        return (Constants::SCOPE_IP === $scope && 2 === count(explode('/', $value))) ? Constants::SCOPE_RANGE : $scope;
    }

    public function addDecision(\DateTime $now, string $durationString, string $dateTimeDurationString, string
    $value, string $type, string $scope = Constants::SCOPE_IP)
    {
        $stopAt = (clone $now)->modify($dateTimeDurationString)->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $body = [
            'capacity' => 0,
            'decisions' => [
              [
                'duration' => $durationString,
                'origin' => 'cscli',
                'scenario' => $type.' for scope/value ('.$scope.'/'.$value.') for '.$durationString.' for PHPUnit tests',
                'scope' => $this->getFinalScope($scope, $value),
                'type' => $type,
                'value' => $value,
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
              'scope' => $this->getFinalScope($scope, $value),
              'value' => $value,
            ],
            'start_at' => $startAt,
            'stop_at' => $stopAt,
          ];
        $result = $this->request('/v1/alerts', null, [$body], 'POST');
        $this->logger->info('', ['message' => 'Decision '.$result[0].' added: '.$body['decisions'][0]['scenario'].'']);
    }
}