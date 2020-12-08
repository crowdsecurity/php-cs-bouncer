<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Psr\Log\LoggerInterface;

/**
 * The LAPI/CAPI REST Client. This is used to retrieve decisions.
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
     * @var RestClient
     */
    private $restClient;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->restClient = new RestClient($this->logger);
    }

    /**
     * Configure this instance.
     */
    public function configure(string $baseUri, int $timeout, string $userAgent, string $apiKey): void
    {
        $this->restClient->configure($baseUri, [
            'User-Agent' => $userAgent,
            'X-Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ], $timeout);
        $this->logger->debug("Api Client User Agent = $userAgent");
        $this->logger->debug("Api Client Key = ***************************");
    }

    /**
     * Request decisions using the specified $filter array.
     */
    public function getFilteredDecisions(array $filter): array
    {
        // TODO P1 keep results filtered for scope=ip or scope=range (we can't do anything with other scopes)
        $decisions = $this->restClient->request('/v1/decisions', $filter);
        $decisions = $decisions ?: [];

        return $decisions;
    }

    /**
     * Request decisions using the stream mode. When the $startup flag is used, all the decisions are returned.
     * Else only the decisions updates (add or remove) from the last stream call are returned.
     */
    public function getStreamedDecisions(bool $startup = false): array
    {
        // TODO P1 keep results filtered for scope=ip or scope=range (we can't do anything with other scopes)
        /** @var array */
        $decisionsDiff = $this->restClient->request('/v1/decisions/stream', $startup ? ['startup' => 'true'] : null);

        return $decisionsDiff;
    }
}
