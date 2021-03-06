<?php

namespace CrowdSecBouncer;

require_once __DIR__.'/templates/captcha.php';
require_once __DIR__.'/templates/access-forbidden.php';

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use IPLib\Factory;
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
     *
     * @param array $config An array with all configuration parameters
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
            $this->config['cache_expiration_for_bad_ip'],
            $this->config['fallback_remediation']
        );
    }

    /**
     * Cap the remediation to a fixed value given in configuration.
     *
     * @param string $remediation The maximum remediation that can ban applied (ex: 'ban', 'captcha', 'bypass')
     *
     * @return string $remediation The resulting remediation to use (ex: 'ban', 'captcha', 'bypass')
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
     * @param string $ip The IP to check
     *
     * @return string the remediation to apply (ex: 'ban', 'captcha', 'bypass')
     */
    public function getRemediationForIp(string $ip): string
    {
        $address = Factory::addressFromString($ip, false);
        if (null === $address) {
            throw new BouncerException("IP $ip format is invalid.");
        }
        $remediation = $this->apiCache->get($address);

        return $this->capRemediationLevel($remediation);
    }

    /**
     * Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP.
     * The input $config should match the TemplateConfiguration input format.
     *
     * @param array $config An array of template configuration parameters
     *
     * @return string The HTML compiled template
     */
    public static function getAccessForbiddenHtmlTemplate(array $config): string
    {
        // Process template configuration.
        $configuration = new TemplateConfiguration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [$config]);

        ob_start();
        displayAccessForbiddenTemplate($config);

        return ob_get_clean();
    }

    /**
     * Returns a default "CrowdSec Captcha" HTML template to display to a web browser using a captchable IP.
     * The input $config should match the TemplateConfiguration input format.
     *
     * @param array $config An array of template configuration parameters
     *
     * @return string The HTML compiled template
     */
    public static function getCaptchaHtmlTemplate(bool $error, string $captchaImageSrc, string $captchaResolutionFormUrl, array $config): string
    {
        // Process template configuration.
        $configuration = new TemplateConfiguration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [$config]);

        ob_start();
        displayCaptchaTemplate($error, $captchaImageSrc, $captchaResolutionFormUrl, $config);

        return ob_get_clean();
    }

    /**
     * Used in stream mode only.
     * This method should be called only to force a cache warm up.
     *
     * @return array "count": number of decisions added, "errors": decisions not added
     */
    public function warmBlocklistCacheUp(): array
    {
        return $this->apiCache->warmUp();
    }

    /**
     * Used in stream mode only.
     * This method should be called periodically (ex: crontab) in a asynchronous way to update the bouncer cache.
     *
     * @return array Number of deleted and new decisions, and errors when processing decisions
     */
    public function refreshBlocklistCache(): array
    {
        return $this->apiCache->pullUpdates();
    }

    /**
     * This method clear the full data in cache.
     *
     * @return bool If the cache has been successfully cleared or not
     */
    public function clearCache(): bool
    {
        return $this->apiCache->clear();
    }

    /**
     * This method prune the cache: it removes all the expired cache items.
     *
     * @return bool If the cache has been successfully pruned or not
     */
    public function pruneCache(): bool
    {
        return $this->apiCache->prune();
    }

    /**
     * Returns the logger instance.
     *
     * @return LoggerInterface the logger used by this library
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Build a captcha couple.
     *
     * @return array an array composed of two items, a "phrase" string representing the phrase and a "inlineImage" representing the image data
     */
    public static function buildCaptchaCouple()
    {
        $captchaBuilder = new CaptchaBuilder();

        return [
            'phrase' => $captchaBuilder->getPhrase(),
            'inlineImage' => $captchaBuilder->build()->inline(),
        ];
    }

    /**
     * Check if the captcha filled by the user is correct or not.
     * We are premissive with the user (0 is interpreted as "o" and 1 in interpretted as "l").
     *
     * @param string $expected The expected phrase
     * @param string $expected The phrase to check (the user input)
     * @param string $ip       Th IP of the use (for logging purpose)
     *
     * @return bool If the captcha input was correct or not
     */
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
     * Test the connection to the cache system (Redis or Memcached).
     *
     * @return bool If the connection was successful or not
     *
     * @throws BouncerException if the connection was not successful
     * */
    public function testConnection()
    {
        return $this->apiCache->testConnection();
    }
}
