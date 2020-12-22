<?php

namespace CrowdSecBouncer;

require_once __DIR__.'/templates/captcha.php';
require_once __DIR__.'/templates/access-forbidden.php';

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Config\Definition\Processor;

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
    private $logger = null;

    /** @var array */
    private $config = [];

    /** @var ApiCache */
    private $apiCache = null;

    /** @var int */
    private $maxRemediationLevelIndex = null;

    public function __construct(AbstractAdapter $cacheAdapter = null, LoggerInterface $logger = null, ApiCache $apiCache = null)
    {
        if (!$logger) {
            $logger = new Logger('null');
            $logger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;
        $this->apiCache = $apiCache ?: new ApiCache($logger, new ApiClient($logger), $cacheAdapter);
    }

    /**
     * Configure this instance.
     */
    public function configure(array $config): void
    {
        // Process input configuration.
        $configuration = new Configuration();
        $processor = new Processor();
        $this->config = $processor->processConfiguration($configuration, [$config]);

        /** @var int */
        $index = array_search(
            $this->config['max_remediation_level'],
            Constants::ORDERED_REMEDIATIONS
        );
        $this->maxRemediationLevelIndex = $index;

        // Configure Api Cache.
        $this->apiCache->configure(
            $this->config['live_mode'],
            $this->config['api_url'],
            $this->config['api_timeout'],
            $this->config['api_user_agent'],
            $this->config['api_key'],
            $this->config['cache_expiration_for_clean_ip'],
            $this->config['cache_expiration_for_bad_ip']
        );
    }

    /**
     * Cap the remediation to a fixed value given in configuration.
     */
    private function capRemediationLevel(string $remediation): string
    {
        $currentIndex = array_search($remediation, Constants::ORDERED_REMEDIATIONS);
        if ($currentIndex < $this->maxRemediationLevelIndex) {
            return Constants::ORDERED_REMEDIATIONS[$this->maxRemediationLevelIndex];
        }

        return $remediation;
    }

    /**
     * Get the remediation for the specified IP. This method use the cache layer.
     * In live mode, when no remediation was found in cache,
     * the cache system will call the API to check if there is a decision.
     *
     * @return string the remediation to apply (ex: 'ban', 'captcha', 'bypass')
     */
    public function getRemediationForIp(string $ip): string
    {
        $intIp = ip2long($ip);
        if (false === $intIp) {
            throw new BouncerException("IP $ip should looks like x.x.x.x, with x in 0-255. Ex: 1.2.3.4");
        }
        $remediation = $this->apiCache->get(long2ip($intIp));

        return $this->capRemediationLevel($remediation);
    }

    /**
     * Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP.
     */
    public static function getAccessForbiddenHtmlTemplate(bool $hideCrowdSecMentions = false): string
    {
        ob_start();
        displayAccessForbiddenTemplate($hideCrowdSecMentions);

        return ob_get_clean();
    }

    /**
     * Returns a default "CrowdSec Captcha" HTML template to display to a web browser using a captchable IP.
     */
    public static function getCaptchaHtmlTemplate(bool $error, string $captchaImageSrc, string $captchaResolutionFormUrl, bool $hideCrowdSecMentions = false): string
    {
        ob_start();
        displayCaptchaTemplate($error, $captchaImageSrc, $captchaResolutionFormUrl, $hideCrowdSecMentions);

        return ob_get_clean();
    }

    /**
     * Used in stream mode only.
     * This method should be called only to force a cache warm up.
     *
     * @return int number of decisions added
     */
    public function warmBlocklistCacheUp(): int
    {
        return $this->apiCache->warmUp();
    }

    /**
     * Used in stream mode only.
     * This method should be called periodically (ex: crontab) in a asynchronous way to update the bouncer cache.
     *
     * @return array number of deleted and new decisions
     */
    public function refreshBlocklistCache(): array
    {
        return $this->apiCache->pullUpdates();
    }

    /**
     * This method clear the full data in cache.
     */
    public function clearCache(): bool
    {
        return $this->apiCache->clear();
    }

    /**
     * This method prune the cache: it removes all the expired cache items.
     */
    public function pruneCache(): bool
    {
        return $this->apiCache->prune();
    }

    /**
     * Returns the logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public static function buildCaptchaCouple()
    {
        $captchaBuilder = new CaptchaBuilder();

        return [
            'phrase' => $captchaBuilder->getPhrase(),
            'inlineImage' => $captchaBuilder->build()->inline(),
        ];
    }

    public function checkCaptcha(string $expected, string $try, string $ip)
    {
        $solved = PhraseBuilder::comparePhrases($expected, $try);
        $this->logger->warning('', [
            'type' => 'CAPTCHA_SOLVED',
            'ip' => $ip,
            'resolution' => $solved,
        ]);

        return $solved;
    }

    /**
     * Test the connection to the cache system (Redis or Memcached)
     * 
     * @throws BouncerException if the connection was not successful
     * */
    public function testConnection()
    {
        return $this->apiCache->testConnection();
    }
}
