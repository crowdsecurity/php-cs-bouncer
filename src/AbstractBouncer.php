<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\LapiClient\RequestHandler\Curl;
use CrowdSec\LapiClient\RequestHandler\FileGetContents;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\AbstractRemediation;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSecBouncer\Fixes\Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use IPLib\Factory;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Processor;

/**
 * The class that apply a bounce.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractBouncer implements BouncerInterface
{
    /** @var array */
    protected $configs = [];
    /** @var LoggerInterface */
    protected $logger;
    /** @var AbstractRemediation */
    protected $remediationEngine;

    public function __construct(
        array $configs,
        AbstractRemediation $remediationEngine,
        LoggerInterface $logger = null)
    {
        if (!$logger) {
            $logger = new Logger('null');
            $logger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;
        $this->configure($configs);
        $this->remediationEngine = $remediationEngine;

        $configs = $this->getConfigs();
        // Clean configs for lighter log
        unset($configs['text'], $configs['color']);
        $this->logger->debug('Instantiate bouncer', [
            'type' => 'BOUNCER_INIT',
            'logger' => \get_class($this->getLogger()),
            'remediation' => \get_class($this->getRemediationEngine()),
            'configs' => $configs
        ]);
    }

    /**
     * Build a captcha couple.
     *
     * @return array an array composed of two items, a "phrase" string representing the phrase and a "inlineImage"
     *     representing the image data
     */
    public static function buildCaptchaCouple(): array
    {
        $captchaBuilder = new CaptchaBuilder();

        return [
            'phrase' => $captchaBuilder->getPhrase(),
            'inlineImage' => $captchaBuilder->build()->inline(),
        ];
    }

    /**
     * This method clear the full data in cache.
     *
     * @return bool If the cache has been successfully cleared or not
     *
     */
    public function clearCache(): bool
    {
        return $this->getRemediationEngine()->clearCache();
    }

    /**
     * Retrieve Bouncer configuration by name
     *
     * @param string $name
     * @return mixed
     */
    public function getConfig(string $name)
    {
        return (isset($this->configs[$name])) ? $this->configs[$name] : null;
    }

    /**
     * Retrieve Bouncer configurations
     *
     * @return array
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * Return cached variables associated to an IP.
     *
     * @param string $cacheTag
     * @param array $names
     * @param string $ip
     * @return array
     */
    public function getIpVariables(string $prefix, array $names, string $ip): array
    {
        $cache = $this->getCache();

        return $cache->getIpVariables($prefix, $names, $ip);
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

    public function getRemediationEngine(): AbstractRemediation
    {
        return $this->remediationEngine;
    }

    /**
     * Get the remediation for the specified IP. This method use the cache layer.
     * In live mode, when no remediation was found in cache,
     * the cache system will call LAPI to check if there is a decision.
     *
     * @param string $ip The IP to check
     *
     * @return string the remediation to apply (ex: 'ban', 'captcha', 'bypass')
     *
     */
    public function getRemediationForIp(string $ip): string
    {
        return $this->capRemediationLevel($this->getRemediationEngine()->getIpRemediation($ip));
    }

    /**
     * This method prune the cache: it removes all the expired cache items.
     *
     * @return bool If the cache has been successfully pruned or not
     */
    public function pruneCache(): bool
    {
        return $this->getRemediationEngine()->pruneCache();
    }

    /**
     * Used in stream mode only.
     * This method should be called periodically (ex: crontab) in an asynchronous way to update the bouncer cache.
     *
     * @return array Number of deleted and new decisions
     *
     */
    public function refreshBlocklistCache(): array
    {
        return $this->getRemediationEngine()->refreshDecisions();
    }

    /**
     * Set a ip variable.
     *
     * @param string $cacheScope
     * @param array $pairs
     * @param string $ip
     * @return void
     */
    public function setIpVariables(string $cacheScope, array $pairs, string $ip, int $duration, string $cacheTag =
    ''): void
    {
        $cache = $this->getCache();
        $cache->setIpVariables($cacheScope, $pairs, $ip, $duration, $cacheTag);
    }

    /**
     * Unset ip variables.
     *
     * @param string $cacheTag
     * @param array $names
     * @param string $ip
     * @return void
     */
    public function unsetIpVariables(string $cacheScope, array $names, string $ip, int $duration, string $cacheTag = ''): void
    {
        $cache = $this->getCache();
        $cache->unsetIpVariables($cacheScope, $names, $ip, $duration, $cacheTag);
    }

    /**
     * Bounce process
     *
     * @return void
     */
    protected function bounceCurrentIp(): void
    {
        // Retrieve the current IP (even if it is a proxy IP) or a testing IP
        $forcedTestIp = $this->getStringConfig('forced_test_ip');
        $ip = !empty($forcedTestIp) ? $forcedTestIp : $this->getRemoteIp();
        $ip = $this->handleForwardedFor($ip, $this->configs);
        $remediation = $this->getRemediationForIp($ip);
        $this->handleRemediation($remediation, $ip);
    }

    /**
     * Check if the captcha filled by the user is correct or not.
     * We are permissive with the user (0 is interpreted as "o" and 1 in interpreted as "l").
     *
     * @param string $expected The expected phrase
     * @param string $try The phrase to check (the user input)
     * @param string $ip The IP of the use (for logging purpose)
     *
     * @return bool If the captcha input was correct or not
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function checkCaptcha(string $expected, string $try, string $ip): bool
    {
        $solved = PhraseBuilder::comparePhrases($expected, $try);
        $this->logger->info('Captcha has been solved', [
            'type' => 'CAPTCHA_SOLVED',
            'ip' => $ip,
            'resolution' => $solved,
        ]);

        return $solved;
    }

    /**
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function displayCaptchaWall(string $ip): void
    {
        $captchaVariables = $this->getIpVariables(
            Constants::CACHE_TAG_CAPTCHA,
            ['crowdsec_captcha_resolution_failed', 'crowdsec_captcha_inline_image'],
            $ip
        );
        $body = $this->getCaptchaHtmlTemplate(
            (bool)$captchaVariables['crowdsec_captcha_resolution_failed'],
            (string)$captchaVariables['crowdsec_captcha_inline_image'],
            ''
        );
        $this->sendResponse($body, 401);
    }

    /**
     * Returns a default "CrowdSec 403" HTML template.
     * The input $config should match the TemplateConfiguration input format.
     *
     *
     * @return string The HTML compiled template
     */
    protected function getAccessForbiddenHtmlTemplate(): string
    {
        $template = new Template('ban.html.twig');

        return $template->render($this->configs);
    }

    protected function getArrayConfig(string $name): array
    {
        return !empty($this->configs[$name]) ? (array)$this->configs[$name] : [];
    }

    protected function getBoolConfig(string $name): bool
    {
        return !empty($this->configs[$name]);
    }

    /**
     * Returns a default "CrowdSec Captcha (401)" HTML template.
     *
     * @param bool $error
     * @param string $captchaImageSrc
     * @param string $captchaResolutionFormUrl
     * @return string
     */
    protected function getCaptchaHtmlTemplate(
        bool $error,
        string $captchaImageSrc,
        string $captchaResolutionFormUrl
    ): string {
        $template = new Template('captcha.html.twig');

        return $template->render(array_merge(
            $this->configs,
            [
                'error' => $error,
                'captcha_img' => $captchaImageSrc,
                'captcha_resolution_url' => $captchaResolutionFormUrl
            ]
        ));
    }

    protected function getIntegerConfig(string $name): int
    {
        return !empty($this->configs[$name]) ? (int)$this->configs[$name] : 0;
    }

    protected function getStringConfig(string $name): string
    {
        return !empty($this->configs[$name]) ? (string)$this->configs[$name] : '';
    }

    /**
     * @return array [[string, string], ...] Returns IP ranges to trust as proxies as an array of comparables ip bounds
     */
    protected function getTrustForwardedIpBoundsList(): array
    {
        return $this->getArrayConfig('trust_ip_forward_array');
    }

    /**
     * @return void
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function handleBanRemediation(): void
    {
        $body = $this->getAccessForbiddenHtmlTemplate();
        $this->sendResponse($body, 403);
    }

    protected function handleCache(array $configs, LoggerInterface $logger): AbstractCache{

        $cacheSystem = $configs['cache_system'] ?? Constants::CACHE_SYSTEM_PHPFS;
        switch ($cacheSystem) {
            case Constants::CACHE_SYSTEM_PHPFS:
                $cache =  new PhpFiles($configs, $logger);
                break;
            case Constants::CACHE_SYSTEM_MEMCACHED:
                $cache =  new Memcached($configs, $logger);
                break;
            case Constants::CACHE_SYSTEM_REDIS:
                $cache =  new Redis($configs, $logger);
                break;
            default:
                throw new BouncerException("Unknown selected cache technology: $cacheSystem");
        }

        return $cache;
    }

    /**
     * @param string $ip
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function handleCaptchaRemediation(string $ip)
    {
        // Check captcha resolution form
        $this->handleCaptchaResolutionForm($ip);
        $cachedCaptchaVariables = $this->getIpVariables(
            Constants::CACHE_TAG_CAPTCHA,
            ['crowdsec_captcha_has_to_be_resolved'],
            $ip
        );
        $mustResolve = false;
        if (null === $cachedCaptchaVariables['crowdsec_captcha_has_to_be_resolved']) {
            // Set up the first captcha remediation.
            $mustResolve = true;
            $captchaCouple = $this->buildCaptchaCouple();
            $captchaVariables = [
                'crowdsec_captcha_phrase_to_guess' => $captchaCouple['phrase'],
                'crowdsec_captcha_inline_image' => $captchaCouple['inlineImage'],
                'crowdsec_captcha_has_to_be_resolved' => true,
                'crowdsec_captcha_resolution_failed' => false,
                'crowdsec_captcha_resolution_redirect' => 'POST' === $this->getHttpMethod() &&
                                                          !empty($_SERVER['HTTP_REFERER'])
                    ? $_SERVER['HTTP_REFERER'] : '/',
            ];
            $duration = $this->getIntegerConfig('captcha_cache_duration');
            $this->setIpVariables(
                Constants::CACHE_TAG_CAPTCHA, $captchaVariables, $ip, $duration, Constants::CACHE_TAG_CAPTCHA
            );
        }

        // Display captcha page if this is required.
        if ($cachedCaptchaVariables['crowdsec_captcha_has_to_be_resolved'] || $mustResolve) {
            $this->displayCaptchaWall($ip);
        }
    }

    /**
     * @param string $ip
     * @return void
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function handleCaptchaResolutionForm(string $ip): void
    {
        $cachedCaptchaVariables = $this->getIpVariables(
            Constants::CACHE_TAG_CAPTCHA,
            [
                'crowdsec_captcha_has_to_be_resolved',
                'crowdsec_captcha_phrase_to_guess',
                'crowdsec_captcha_resolution_redirect',
            ],
            $ip
        );
        if ($this->shouldEarlyReturn($cachedCaptchaVariables, $ip)) {
            return;
        }

        // Handle a captcha resolution try
        if (
            null !== $this->getPostedVariable('phrase')
            && null !== $cachedCaptchaVariables['crowdsec_captcha_phrase_to_guess']
        ) {
            $duration = $this->getIntegerConfig('captcha_cache_duration');
            if (
                $this->checkCaptcha(
                    (string)$cachedCaptchaVariables['crowdsec_captcha_phrase_to_guess'],
                    (string)$this->getPostedVariable('phrase'),
                    $ip
                )
            ) {
                // User has correctly filled the captcha
                $this->setIpVariables(
                    Constants::CACHE_TAG_CAPTCHA,
                    ['crowdsec_captcha_has_to_be_resolved' => false],
                    $ip,
                    $duration,
                    Constants::CACHE_TAG_CAPTCHA
                );
                $unsetVariables = [
                    'crowdsec_captcha_phrase_to_guess',
                    'crowdsec_captcha_inline_image',
                    'crowdsec_captcha_resolution_failed',
                    'crowdsec_captcha_resolution_redirect',
                ];
                $this->unsetIpVariables(
                    Constants::CACHE_TAG_CAPTCHA, $unsetVariables, $ip, $duration, Constants::CACHE_TAG_CAPTCHA
                );
                $redirect = $cachedCaptchaVariables['crowdsec_captcha_resolution_redirect'] ?? '/';
                header("Location: $redirect");
                exit(0);
            } else {
                // The user failed to resolve the captcha.
                $this->setIpVariables(
                    Constants::CACHE_TAG_CAPTCHA,
                    ['crowdsec_captcha_resolution_failed' => true],
                    $ip,
                    $duration,
                    Constants::CACHE_TAG_CAPTCHA
                );
            }
        }
    }

    protected function handleClient(array $configs, LoggerInterface $logger)
    {
        $requestHandler = empty($configs['use_curl']) ? new FileGetContents($configs) : new Curl($configs);

        return new BouncerClient($configs, $requestHandler, $logger);
    }

    /**
     * Handle X-Forwarded-For HTTP header to retrieve the IP to bounce
     *
     * @param string $ip
     * @param array $configs
     * @return string
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function handleForwardedFor(string $ip, array $configs): string
    {
        $forwardedIp = null;
        if (empty($configs['forced_test_forwarded_ip'])) {
            $xForwardedForHeader = $this->getHttpRequestHeader('X-Forwarded-For');
            if (null !== $xForwardedForHeader) {
                $ipList = array_map('trim', array_values(array_filter(explode(',', $xForwardedForHeader))));
                $forwardedIp = end($ipList);
            }
        } elseif ($configs['forced_test_forwarded_ip'] === Constants::X_FORWARDED_DISABLED) {
            $this->logger->debug('X-Forwarded-for usage is disabled', [
                'type' => 'DISABLED_X_FORWARDED_FOR_USAGE',
                'original_ip' => $ip,
            ]);
        } else {
            $forwardedIp = (string) $configs['forced_test_forwarded_ip'];
        }

        if (is_string($forwardedIp) && $this->shouldTrustXforwardedFor($ip)) {
            $ip = $forwardedIp;
        } else {
            $this->logger->warning('Detected IP is not allowed for X-Forwarded-for usage', [
                'type' => 'NON_AUTHORIZED_X_FORWARDED_FOR_USAGE',
                'original_ip' => $ip,
                'x_forwarded_for_ip' => is_string($forwardedIp) ? $forwardedIp : 'type not as expected',
            ]);
        }
        return $ip;
    }

    /**
     * Handle remediation for some IP.
     *
     * @param string $remediation
     * @param string $ip
     * @return void
     */
    protected function handleRemediation(string $remediation, string $ip)
    {
        switch ($remediation) {
            case Constants::REMEDIATION_CAPTCHA:
                $this->handleCaptchaRemediation($ip);
                break;
            case Constants::REMEDIATION_BAN:
                $this->handleBanRemediation();
                break;
            case Constants::REMEDIATION_BYPASS:
            default:
        }
    }

    /**
     * @param array $configs
     * @param string $loggerName
     * @return void
     */
    protected function initFileLogger(array $configs, string $loggerName): LoggerInterface
    {
        $logger = new Logger($loggerName);
        $logDir = $configs['log_directory_path'] ?? __DIR__ . '/.logs';
        if (empty($configs['disable_prod_log'])) {
            $logPath = $logDir . '/prod.log';
            $fileHandler = new RotatingFileHandler($logPath, 0, Logger::INFO);
            $fileHandler->setFormatter(new LineFormatter("%datetime%|%level%|%message%|%context%\n"));
            $logger->pushHandler($fileHandler);
        }

        // Set custom readable logger when debug=true
        if (!empty($configs['debug_mode'])) {
            $debugLogPath = $logDir . '/debug.log';
            $debugFileHandler = new RotatingFileHandler($debugLogPath, 0, Logger::DEBUG);
            $debugFileHandler->setFormatter(new LineFormatter("%datetime%|%level%|%message%|%context%\n"));
            $logger->pushHandler($debugFileHandler);
        }

        return $logger;
    }

    protected function shouldTrustXforwardedFor(string $ip): bool
    {
        $parsedAddress = Factory::parseAddressString($ip, 3);
        if (null === $parsedAddress) {
            $this->logger->warning('IP is invalid', [
                'type' => 'INVALID_INPUT_IP',
                'ip' => $ip,
            ]);

            return false;
        }
        $comparableAddress = $parsedAddress->getComparableString();

        foreach ($this->getTrustForwardedIpBoundsList() as $comparableIpBounds) {
            if ($comparableAddress >= $comparableIpBounds[0] && $comparableAddress <= $comparableIpBounds[1]) {
                return true;
            }
        }

        return false;
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
        $orderedRemediations = $this->getRemediationEngine()->getConfig('ordered_remediations')??[];

        $bouncingLevel = $this->getStringConfig('bouncing_level')??Constants::BOUNCING_LEVEL_NORMAL;
        // Compute max remediation level
        switch ($bouncingLevel) {
            case Constants::BOUNCING_LEVEL_DISABLED:
                $maxRemediationLevel = Constants::REMEDIATION_BYPASS;
                break;
            case Constants::BOUNCING_LEVEL_FLEX:
                $maxRemediationLevel = Constants::REMEDIATION_CAPTCHA;
                break;
            case Constants::BOUNCING_LEVEL_NORMAL:
                $maxRemediationLevel = Constants::REMEDIATION_BAN;
                break;
            default:
                throw new BouncerException("Unknown $bouncingLevel");
        }


        $currentIndex = (int) array_search($remediation, $orderedRemediations);
        $maxIndex = (int) array_search(
            $maxRemediationLevel,
            $orderedRemediations
        );
        if ($currentIndex < $maxIndex) {
            return $orderedRemediations[$maxIndex];
        }

        return $remediation;
    }

    /**
     * Configure this instance.
     *
     * @param array $config An array with all configuration parameters
     *
     */
    private function configure(array $config): void
    {
        // Process and validate input configuration.
        $configuration = new Configuration();
        $processor = new Processor();
        $this->configs = $processor->processConfiguration($configuration, [$configuration->cleanConfigs($config)]);
    }

    private function getCache(): AbstractCache
    {
        return $this->getRemediationEngine()->getCacheStorage();
    }

    /**
     * @param array $cachedCaptchaVariables
     * @param string $ip
     * @return bool
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function shouldEarlyReturn(array $cachedCaptchaVariables, string $ip): bool
    {
        $result = false;
        if (\in_array($cachedCaptchaVariables['crowdsec_captcha_has_to_be_resolved'], [null, false])) {
            // Early return if no captcha has to be resolved.
            $result = true;
        } elseif ('POST' !== $this->getHttpMethod() || null === $this->getPostedVariable('crowdsec_captcha')) {
            // Early return if no form captcha form has been filled.
            $result = true;
        } elseif (null !== $this->getPostedVariable('refresh') && (int)$this->getPostedVariable('refresh')) {
            // Handle image refresh.
            // Generate new captcha image for the user
            $captchaCouple = Bouncer::buildCaptchaCouple();
            $captchaVariables = [
                'crowdsec_captcha_phrase_to_guess' => $captchaCouple['phrase'],
                'crowdsec_captcha_inline_image' => $captchaCouple['inlineImage'],
                'crowdsec_captcha_resolution_failed' => false,
            ];
            $duration = $this->getIntegerConfig('captcha_cache_duration');
            $this->setIpVariables(
                Constants::CACHE_TAG_CAPTCHA, $captchaVariables, $ip, $duration, Constants::CACHE_TAG_CAPTCHA
            );

            $result = true;
        }

        return $result;
    }
}
