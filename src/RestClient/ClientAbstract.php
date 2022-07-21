<?php

declare(strict_types=1);

namespace CrowdSecBouncer\RestClient;

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

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Configure this instance.
     */
    abstract public function configure(string $baseUri, array $headers, int $timeout): void;

    /**
     * Send an HTTP request and parse its JSON result if any.
     */
    abstract public function request(string $endpoint, array $queryParams = null, array $bodyParams = null, string $method = 'GET', array $headers = null, int $timeout = null): ?array;
}
