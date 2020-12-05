<?php

namespace CrowdSecBouncer;

use Monolog\Handler\NullHandler;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Config\Definition\Processor;
use Psr\Log\LoggerInterface;
use \Monolog\Logger;

/**
 * The main Class of this package. This is the first entry point of any PHP Bouncers using this library.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Bouncer
{
    /** @var LoggerInterface */
    private $logger;

    /** @var array */
    private $config;

    /** @var ApiCache */
    private $apiCache;

    public function __construct(ApiCache $apiCache = null, LoggerInterface $logger = null)
    {
        if (!$logger) {
            $loggger = new Logger('null');
            $loggger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;
        $this->apiCache = $apiCache ?: new ApiCache(new ApiClient($logger), $logger);
    }

    /**
     * Configure this instance.
     */
    public function configure(array $config, AbstractAdapter $cacheAdapter): void
    {
        // Process input configuration.
        $configuration = new Configuration();
        $processor = new Processor();
        $this->config = $processor->processConfiguration($configuration, [$config]);

        // Configure Api Cache.
        $this->apiCache->configure(
            $cacheAdapter,
            $this->config['rupture_mode'],
            $this->config['api_url'],
            $this->config['api_timeout'],
            $this->config['api_user_agent'],
            $this->config['api_token'],
            $this->config['cache_expiration_for_clean_ip']
        );
    }

    /**
     * Get the remediation for the specified IP. This method use the cache layer.
     * In rupture mode, when no remediation was found in cache, the cache system will call the API to check if there is a decision.
     *
     * @return string the remediation to apply (ex: 'ban', 'captcha', 'bypass')
     */
    public function getRemediationForIp(string $ip): ?string
    {
        $intIp = ip2long($ip);
        if (false === $intIp) {
            throw new BouncerException("IP $ip should looks like x.x.x.x, with x in 0-255. Ex: 1.2.3.4");
        }

        return $this->apiCache->get($intIp);
    }

    /**
     * Returns a default "CrowdSec 403" HTML template to display to a web browser using a ban IP.
     */
    public function getDefault403Template(): string
    {
        return '<html><body><h1>Access forbidden.</h1><p>You have been blocked by CrowdSec. Please contact our technical support if you think it is an error.</p></body></html>';
    }

    /**
     * Used in stream mode only.
     * This method should be called once when installing the PHP Bouncer.
     */
    public function warmBlocklistCacheUp(): void
    {
        $this->apiCache->warmUp();
    }

    /**
     * Used in stream mode only.
     * This method should be called periodically (ex: crontab) in a asynchronous way to update the bouncer cache.
     */
    public function refreshBlocklistCache(): void
    {
        $this->apiCache->pullUpdates();
    }

    /**
     * Browse the remediations cache.
     */
    public function loadPaginatedBlocklistFromCache(int $page = 1, int $itemPerPage = 10): array
    {
        // TODO P3 Implement this.
        // TODO P3 Implement advanced filters, ex: sort_by=[], filters[type[], origin[], scope[], value[], ip_range[], duration_range[], scenario[], simulated]=null
        return [];
    }

    /**
     * Browse the bouncer technical logs.
     * TODO P3 Code this.
     */
    public function loadPaginatedLogs(int $page = 1, int $itemPerPage = 10): array
    {
        // TODO P3 Implement this.
        return [];
    }
}
