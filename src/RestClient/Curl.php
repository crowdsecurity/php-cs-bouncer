<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace CrowdSecBouncer\RestClient;

use CrowdSecBouncer\BouncerException;

class Curl extends ClientAbstract
{

    private $headers = [];

    /**
     * Configure this instance.
     */
    public function configure(string $baseUri, array $headers, int $timeout): void
    {
        $this->baseUri = $baseUri;
        $this->timeout = $timeout;
        $this->headers = $headers;

        $this->logger->debug('', [
            'type' => 'REST_CLIENT_INIT',
            'request_handler' => 'cURL',
            'base_uri' => $this->baseUri,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Send an HTTP request using cURL and parse its JSON result if any.
     *
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array|null $bodyParams
     * @param string $method
     * @param array|null $headers
     * @param int|null $timeout
     * @return array|null
     * @throws BouncerException
     */
    public function request(
        string $endpoint,
        array  $queryParams = null,
        array  $bodyParams = null,
        string $method = 'GET',
        array $headers = null,
        int    $timeout = null): ?array
    {
        if (!$this->baseUri) {
            throw new BouncerException('Base URI is required.');
        }

        $handle = curl_init();

        $curlOptions = $this->createOptions($endpoint, $queryParams, $bodyParams, $method, $headers?:$this->headers);

        curl_setopt_array($handle, $curlOptions);

        $response = $this->exec($handle);

        if (false === $response) {
            throw new BouncerException('Unexpected CURL call failure: ' . curl_error($handle));
        }

        $statusCode = $this->getResponseHttpCode($handle);
        if (empty($statusCode)) {
            throw new BouncerException('Unexpected empty response http code');
        }

        curl_close($handle);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = "Unexpected response status from $this->baseUri$endpoint: $statusCode\n" . $response;
            throw new BouncerException($message);
        }

        return json_decode($response, true);
    }

    /**
     * Retrieve Curl options.
     *
     * @param $endpoint
     * @param array|null $queryParams
     * @param array|null $bodyParams
     * @param string $method
     * @param array|null $headers
     * @return array
     * @throws BouncerException
     */
    private function createOptions($endpoint,
                                   ?array $queryParams,
                                   ?array $bodyParams,
                                   string $method, ?array $headers
    ): array
    {
        $url = $this->baseUri . $endpoint;
        if (!isset($headers['User-Agent'])) {
            throw new BouncerException('User agent is required');
        }
        $options = array(
            \CURLOPT_HEADER => false,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_USERAGENT => $headers['User-Agent'],
        );

        $options[\CURLOPT_HTTPHEADER] = array();
        foreach ($headers as $key => $values) {
            foreach (\is_array($values) ? $values : array($values) as $value) {
                $options[\CURLOPT_HTTPHEADER][] = sprintf('%s:%s', $key, $value);
            }
        }

        if ('POST' === strtoupper($method)) {
            $parameters = $bodyParams;
            $options[\CURLOPT_POST] = true;
            $options[\CURLOPT_CUSTOMREQUEST] = 'POST';
            $options[\CURLOPT_POSTFIELDS] = json_encode($parameters);
        } elseif ('GET' === strtoupper($method)) {
            $parameters = $queryParams;
            $options[\CURLOPT_POST] = false;
            $options[\CURLOPT_CUSTOMREQUEST] = 'GET';
            $options[\CURLOPT_HTTPGET] = true;

            if (!empty($parameters)) {
                $url .= strpos($url, '?') ? '&' : '?';
                $url .= http_build_query($parameters);
            }
        } elseif ('DELETE' === strtoupper($method)) {
            $options[\CURLOPT_POST] = false;
            $options[\CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        $options[\CURLOPT_URL] = $url;
        if ($this->timeout > 0) {
            $options[\CURLOPT_TIMEOUT] = $this->timeout;
        }

        // $options[CURLOPT_VERBOSE] = true;

        return $options;
    }

    protected function getResponseHttpCode($handle)
    {
        return curl_getinfo($handle, \CURLINFO_HTTP_CODE);
    }

    /**
     * @param $handle
     *
     * @return bool|string
     */
    protected function exec($handle)
    {
        return curl_exec($handle);
    }
}
