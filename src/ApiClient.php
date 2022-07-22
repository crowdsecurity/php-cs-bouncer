<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Psr\Log\LoggerInterface;
use CrowdSecBouncer\RestClient\FileGetContents;
use CrowdSecBouncer\RestClient\Curl;
use CrowdSecBouncer\RestClient\ClientAbstract;

/**
 * The LAPI REST Client. This is used to retrieve decisions.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class ApiClient
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * @var ClientAbstract
     */
    private $restClient;

    public function __construct(LoggerInterface $logger, array $configs = [])
    {
        $this->logger = $logger;
        $useCurl = !empty($configs['use_curl']);
        $userAgent = $configs['api_user_agent'];
        $configs['headers'] = [
            'User-Agent' => $configs['api_user_agent'],
            'X-Api-Key' => $configs['api_key'],
            'Accept' => 'application/json',
        ];

        $this->restClient = $useCurl ? new Curl($this->logger, $configs) : new FileGetContents($this->logger, $configs);
        $this->logger->debug('', [
            'type' => 'API_CLIENT_INIT',
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Request decisions using the specified $filter array.
     * @throws BouncerException
     */
    public function getFilteredDecisions(array $filter): array
    {
        return $this->restClient->request('/v1/decisions', $filter) ?: [];
    }

    /**
     * Request decisions using the stream mode. When the $startup flag is used, all the decisions are returned.
     * Else only the decisions updates (add or remove) from the last stream call are returned.
     * @throws BouncerException
     */
    public function getStreamedDecisions(
        bool $startup = false,
        array $scopes = [Constants::SCOPE_IP, Constants::SCOPE_RANGE]
    ): array {
        /** @var array */
        return $this->restClient->request(
            '/v1/decisions/stream',
            ['startup' => $startup ? 'true' : 'false', 'scopes' => implode(',', $scopes)]
        );
    }
}
