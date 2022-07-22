<?php

declare(strict_types=1);

namespace CrowdSecBouncer\RestClient;

use CrowdSecBouncer\Constants;
use Psr\Log\LoggerInterface;

/**
 * The low level REST Client.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
abstract class ClientAbstract
{
    /** @var int|null */
    protected $timeout = null;

    /** @var string|null */
    protected $baseUri = null;

    /** @var array */
    protected $headers = [];

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger, array $configs = [])
    {
        $this->logger = $logger;
        $this->baseUri = $configs['api_url'];
        $this->timeout = $configs['api_timeout'] ?? Constants::API_TIMEOUT;
        $this->headers = $configs['headers'];

        $this->logger->debug('', [
            'type' => 'REST_CLIENT_INIT',
            'request_handler' => get_class($this),
            'base_uri' => $this->baseUri,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Send an HTTP request and parse its JSON result if any.
     */
    abstract public function request(
        string $endpoint,
        array $queryParams = null,
        array $bodyParams = null,
        string $method = 'GET',
        array $headers = null,
        int $timeout = null
    ): ?array;
}
