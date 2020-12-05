<?php

declare(strict_types=1);

use CrowdSecBouncer\RestClient;
use CrowdSecBouncer\Constants;
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
            'User-Agent' => Constants::BASE_USER_AGENT
        ];
        $this->watcherClient = new RestClient($this->logger);
        $this->watcherClient->configure($apiUrl, $this->baseHeaders, 2);
        $this->logger->info('Watcher client initialized');
    }

    /** Set the initial watcher state using the alert_base_state.json file */
    public function setInitialState(): void
    {
        $this->logger->info('Set initial state');
        $this->deleteAllDecisions();
        $this->addBaseDecisions();
    }

    /** Set the initial watcher state using the alert_updated_state.json file */
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
                'password' => self::WATCHER_PASSWORD
            ];
            /** @var array */
            $credentials = $this->watcherClient->request('/v1/watchers/login', null, $data, 'POST');
            $this->token = $credentials['token'];
            $this->baseHeaders['Authorization'] = 'Bearer ' . $this->token;
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
        /** @var string */
        $jsonString = file_get_contents(__DIR__ . '/data/alerts_base_state.json');
        $data = json_decode($jsonString, true);

        $now = new DateTime();
        $stopAt = (clone $now)->modify('+1 day')->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $ipCaptcha12h = $data[0];
        $ipCaptcha12h['start_at'] = $startAt;
        $ipCaptcha12h['stop_at'] = $stopAt;
        $result = $this->request('/v1/alerts', null, [$ipCaptcha12h], 'POST');
        $this->logger->info('Decision '.$result[0]. ' added: '.$ipCaptcha12h['decisions'][0]['scenario'].'');

        $rangeBan24h = $data[1];
        $rangeBan24h['start_at'] = $startAt;
        $rangeBan24h['stop_at'] = $stopAt;
        $result = $this->request('/v1/alerts', null, [$rangeBan24h], 'POST');
        $this->logger->info('Decision '.$result[0]. ' added: '.$rangeBan24h['decisions'][0]['scenario'].'');
    }

    /**
     * Add new decisions (captcha 3.4.5.6 for 36h + ban 3.4.5.6/30 for 48h)
     */
    public function addNewDecisions(): void
    {
        /** @var string */
        $jsonString = file_get_contents(__DIR__ . '/data/alerts_updated_state.json');
        $data = json_decode($jsonString, true);

        $now = new DateTime();
        $stopAt = (clone $now)->modify('+1 day')->format('Y-m-d\TH:i:s.000\Z');
        $startAt = $now->format('Y-m-d\TH:i:s.000\Z');

        $ipBan36h = $data[0];
        $ipBan36h['start_at'] = $startAt;
        $ipBan36h['stop_at'] = $stopAt;
        $result = $this->request('/v1/alerts', null, [$ipBan36h], 'POST');
        $this->logger->info('Decision '.$result[0]. ' added: '.$ipBan36h['decisions'][0]['scenario'].'');

        $rangeCaptcha48h = $data[1];
        $rangeCaptcha48h['start_at'] = $startAt;
        $rangeCaptcha48h['stop_at'] = $stopAt;
        $result = $this->request('/v1/alerts', null, [$rangeCaptcha48h], 'POST');
        $this->logger->info('Decision '.$result[0]. ' added: '.$rangeCaptcha48h['decisions'][0]['scenario'].'');
    }
}
