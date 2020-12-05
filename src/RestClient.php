<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

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
class RestClient
{
    /** @var string */
    private $headerString;

    /** @var int */
    private $timeout;

    /** @var string */
    private $baseUri;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Configure this instance.
     */
    public function configure(string $baseUri, array $headers, int $timeout): void
    {
        $this->baseUri = $baseUri;
        $this->headerString = $this->convertHeadersToString($headers);
        $this->timeout = $timeout;

        $this->logger->debug("Rest client base URI: ".$this->baseUri);
        $this->logger->debug("Rest client headers: ***************************");
        $this->logger->debug("Rest client timeout: ".$this->timeout);
    }

    /**
     * Convert an key-value array of headers to the official HTTP header string.
     */
    private function convertHeadersToString(array $headers): string
    {
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        return $headerString;
    }

    /**
     * Send an HTTP request using the file_get_contents and parse its JSON result if any.
     *
     * @throws BouncerException when the reponse status is not 2xx.
     *
     * TODO P3 test
     */
    public function request(string $endpoint, array $queryParams = null, array $bodyParams = null, string $method = 'GET', array $headers = null, int $timeout = null): ?array
    {
        if ($queryParams) {
            $endpoint .= '?'.http_build_query($queryParams);
        }
        $header = $headers ? $this->convertHeadersToString($headers) : $this->headerString;
        $config = [
            'http' => [
                'method' => $method,
                'header' => $header,
                'timeout' => $timeout ?: $this->timeout,
            ],
        ];
        if ($bodyParams) {
            $config['http']['content'] = json_encode($bodyParams);
        }
        $context = stream_context_create($config);

        $this->logger->debug("$method ".$this->baseUri.$endpoint);

        $response = file_get_contents($this->baseUri.$endpoint, false, $context);
        if (false === $response) {
            throw new BouncerException('Unexpected HTTP call failure.');
        }
        $statusLine = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
        $status = (int) $match[1];
        if ($status < 200 || $status >= 300) {
            throw new BouncerException("unexpected response status: {$statusLine}\n".$response);
        }
        $data = json_decode($response, true);

        return $data;
    }
}
