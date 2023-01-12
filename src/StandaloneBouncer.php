<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\RemediationEngine\CacheStorage\CacheStorageException;
use Exception;
use IPLib\Factory;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSec\RemediationEngine\Logger\FileLog;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * The class that apply a bounce in standalone mode.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
class StandaloneBouncer extends AbstractBouncer
{
    /**
     * @throws BouncerException
     * @throws CacheStorageException
     */
    public function __construct(array $configs, LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new FileLog($configs, 'php_standalone_bouncer');
        $configs = $this->handleTrustedIpsConfig($configs);
        $configs['user_agent_version'] = Constants::VERSION;
        $configs['user_agent_suffix'] = 'Standalone';
        $client = $this->handleClient($configs, $this->logger);
        $cache = $this->handleCache($configs, $this->logger);
        $remediation = new LapiRemediation($configs, $client, $cache, $this->logger);

        parent::__construct($configs, $remediation, $this->logger);
    }

    /**
     * The current HTTP method.
     */
    public function getHttpMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? "";
    }

    /**
     * @param string $name Ex: "X-Forwarded-For"
     */
    public function getHttpRequestHeader(string $name): ?string
    {
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        if (!\array_key_exists($headerName, $_SERVER)) {
            return null;
        }

        return is_string($_SERVER[$headerName]) ? $_SERVER[$headerName] : null;
    }

    /**
     * Get the value of a posted field.
     */
    public function getPostedVariable(string $name): ?string
    {
        if (!isset($_POST[$name])) {
            return null;
        }

        return is_string($_POST[$name]) ? $_POST[$name] : null;
    }

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getRemoteIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? "";
    }

    /**
     * If there is any technical problem while bouncing, don't block the user. Bypass bouncing and log the error.
     *
     * @return bool
     * @throws BouncerException
     * @throws CacheException
     * @throws CacheStorageException
     * @throws InvalidArgumentException
     * @throws \Symfony\Component\Cache\Exception\InvalidArgumentException
     */
    public function safelyBounce(): bool
    {
        $result = false;
        set_error_handler(function ($errno, $errstr) {
            throw new BouncerException("$errstr (Error level: $errno)");
        });
        try {
            if ($this->shouldBounceCurrentIp()) {
                $this->bounceCurrentIp();
                $result = true;
            }
        } catch (Exception $e) {
            $this->logger->error('Something went wrong during bouncing', [
                'type' => 'EXCEPTION_WHILE_BOUNCING',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if (true === $this->getConfig('display_errors')) {
                throw $e;
            }
        }
        restore_error_handler();

        return $result;
    }

    /**
     * Send HTTP response.
     * @throws BouncerException
     */
    public function sendResponse(?string $body, int $statusCode = 200): void
    {
        switch ($statusCode) {
            case 200:
                header('HTTP/1.0 200 OK');
                break;
            case 401:
                header('HTTP/1.0 401 Unauthorized');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                break;
            case 403:
                header('HTTP/1.0 403 Forbidden');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                break;
            default:
                throw new BouncerException("Unhandled code $statusCode");
        }
        if (null !== $body) {
            echo $body;
        }
        exit();
    }

    /**
     * If the current IP should be bounced or not, matching custom business rules.
     */
    public function shouldBounceCurrentIp(): bool
    {
        $excludedURIs = $this->getConfig('excluded_uris') ?? [];
        if (isset($_SERVER['REQUEST_URI']) && \in_array($_SERVER['REQUEST_URI'], $excludedURIs)) {
            $this->logger->debug('Will not bounce as URI is excluded', [
                'type' => 'SHOULD_NOT_BOUNCE',
                'message' => 'This URI is excluded from bouncing: ' . $_SERVER['REQUEST_URI'],
            ]);

            return false;
        }
        $bouncingDisabled = (Constants::BOUNCING_LEVEL_DISABLED === $this->getConfig('bouncing_level'));
        if ($bouncingDisabled) {
            $this->logger->debug('Will not bounce as bouncing is disabled', [
                'type' => 'SHOULD_NOT_BOUNCE',
                'message' => Constants::BOUNCING_LEVEL_DISABLED,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Initialize the bouncer.
     *
     */
    private function handleTrustedIpsConfig(array $configs): array
    {
        // Convert array of string to array of array with comparable IPs
        if (isset($configs['trust_ip_forward_array']) && \is_array(($configs['trust_ip_forward_array']))) {
            $forwardConfigs = $configs['trust_ip_forward_array'];
            $finalForwardConfigs = [];
            foreach ($forwardConfigs as $forwardConfig) {
                if (\is_string($forwardConfig)) {
                    $parsedString = Factory::parseAddressString($forwardConfig, 3);
                    if (!empty($parsedString)) {
                        $comparableValue = $parsedString->getComparableString();
                        $finalForwardConfigs[] = [$comparableValue, $comparableValue];
                    }
                } elseif (\is_array($forwardConfig)) {
                    $finalForwardConfigs[] = $forwardConfig;
                }
            }
            $configs['trust_ip_forward_array'] = $finalForwardConfigs;
        }

        return $configs;
    }
}
