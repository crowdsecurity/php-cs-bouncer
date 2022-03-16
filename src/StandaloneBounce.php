<?php

namespace CrowdSecBouncer;

use ErrorException;
use Exception;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

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
class StandaloneBounce extends AbstractBounce implements IBounce
{
    /** @var AbstractAdapter */
    protected $cacheAdapter;

    /**
     * @var string
     */
    protected $session_name;

    /**
     * Initialize the bouncer.
     *
     * @throws CacheException
     * @throws ErrorException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function init(array $configs, array $forcedConfigs = []): Bouncer
    {
        if (\PHP_SESSION_NONE === session_status()) {
            $this->session_name = session_name('crowdsec');
            session_start();
        }
        $this->settings = $configs;
        if (\is_array($forcedConfigs)) {
            $this->settings = array_merge($this->settings, $forcedConfigs);
        }
        $this->setDebug($this->getBoolSettings('debug_mode'));
        $this->setDisplayErrors($this->getBoolSettings('display_errors'));
        $this->initLogger();

        return $this->getBouncerInstance($this->settings);
    }

    /**
     * @throws CacheException
     * @throws ErrorException
     */
    private function getCacheAdapterInstance(): AbstractAdapter
    {
        // Singleton for this function
        if ($this->cacheAdapter) {
            return $this->cacheAdapter;
        }

        $cacheSystem = $this->getStringSettings('cache_system');
        switch ($cacheSystem) {
            case Constants::CACHE_SYSTEM_PHPFS:
                $this->cacheAdapter = new PhpFilesAdapter('', 0, $this->getStringSettings('fs_cache_path'));
                break;

            case Constants::CACHE_SYSTEM_MEMCACHED:
                $memcachedDsn = $this->getStringSettings('memcached_dsn');
                if (empty($memcachedDsn)) {
                    throw new BouncerException('The selected cache technology is Memcached.'.' Please set a Memcached DSN or select another cache technology.');
                }

                $this->cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedDsn));
                break;

            case Constants::CACHE_SYSTEM_REDIS:
                $redisDsn = $this->getStringSettings('redis_dsn');
                if (empty($redisDsn)) {
                    throw new BouncerException('The selected cache technology is Redis.'.' Please set a Redis DSN or select another cache technology.');
                }

                try {
                    $this->cacheAdapter = new RedisAdapter(RedisAdapter::createConnection($redisDsn));
                } catch (InvalidArgumentException $e) {
                    throw new BouncerException('Error when connecting to Redis.'.' Please fix the Redis DSN or select another cache technology.');
                }

                break;
            default:
                throw new BouncerException('Unknown selected cache technology.');
        }

        return $this->cacheAdapter;
    }

    /**
     * Get the bouncer instance.
     *
     * @throws CacheException
     * @throws ErrorException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getBouncerInstance(array $settings, bool $forceReload = false): Bouncer
    {
        // Singleton for this function (if no reload forcing)
        if ($this->bouncer && !$forceReload) {
            return $this->bouncer;
        }
        $bouncingLevel = $this->getStringSettings('bouncing_level');
        $apiUserAgent = 'Standalone CrowdSec PHP Bouncer/'.Constants::VERSION;
        $apiTimeout = $this->getIntegerSettings('api_timeout');

        // Init Bouncer instance
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
        // Instantiate the bouncer
        try {
            $this->cacheAdapter = $this->getCacheAdapterInstance();
        } catch (InvalidArgumentException $e) {
            throw new BouncerException($e->getMessage());
        }
        // Instantiate bouncer
        $this->bouncer = new Bouncer($this->cacheAdapter, $this->logger);
        // Validate settings
        $this->bouncer->configure([
            // LAPI connection
            'api_key' => $this->getStringSettings('api_key'),
            'api_url' => $this->getStringSettings('api_url'),
            'api_user_agent' => $apiUserAgent,
            'api_timeout' => $apiTimeout > 0 ? $apiTimeout : Constants::API_TIMEOUT,
            // Debug
            'debug_mode' => $this->getBoolSettings('debug_mode'),
            'log_directory_path' => $this->getStringSettings('log_directory_path'),
            'forced_test_ip' => $this->getStringSettings('forced_test_ip'),
            'display_errors' => $this->getBoolSettings('display_errors'),
            // Bouncer
            'bouncing_level' => $bouncingLevel,
            'trust_ip_forward_array' => $this->getArraySettings('trust_ip_forward_array'),
            'fallback_remediation' => $this->getStringSettings('fallback_remediation'),
            'max_remediation_level' => $maxRemediationLevel,
            // Cache settings
            'stream_mode' => $this->getBoolSettings('stream_mode'),
            'cache_system' => $this->getStringSettings('cache_system'),
            'fs_cache_path' => $this->getStringSettings('fs_cache_path'),
            'redis_dsn' => $this->getStringSettings('redis_dsn'),
            'memcached_dsn' => $this->getStringSettings('memcached_dsn'),
            'cache_expiration_for_clean_ip' => $this->getIntegerSettings('clean_ip_cache_duration'),
            'cache_expiration_for_bad_ip' => $this->getIntegerSettings('bad_ip_cache_duration'),
            // Geolocation
            'geolocation' => $this->getArraySettings('geolocation'),
        ]);

        return $this->bouncer;
    }

    public function initLogger(): void
    {
        $this->initLoggerHelper($this->getStringSettings('log_directory_path'), 'php_standalone_bouncer');
    }

    /**
     * @param string $name Ex: "X-Forwarded-For"
     */
    public function getHttpRequestHeader(string $name): ?string
    {
        $headerName = 'HTTP_'.str_replace('-', '_', strtoupper($name));
        if (!\array_key_exists($headerName, $_SERVER)) {
            return null;
        }

        return $_SERVER[$headerName];
    }

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getRemoteIp(): string
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * The current HTTP method.
     */
    public function getHttpMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return array ['hide_crowdsec_mentions': bool, color:[text:['primary' : string, 'secondary' : string, 'button' : string, 'error_message : string' ...]]] (returns an array of option required to build the captcha wall template)
     */
    public function getCaptchaWallOptions(): array
    {
        return [
            'hide_crowdsec_mentions' => $this->getBoolSettings('hide_mentions'),
            'color' => [
              'text' => [
                'primary' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_primary'), \ENT_QUOTES),
                'secondary' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_secondary'), \ENT_QUOTES),
                'button' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_button'), \ENT_QUOTES),
                'error_message' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_error_message'), \ENT_QUOTES),
              ],
              'background' => [
                'page' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_page'), \ENT_QUOTES),
                'container' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_container'), \ENT_QUOTES),
                'button' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_button'), \ENT_QUOTES),
                'button_hover' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_button_hover'), \ENT_QUOTES),
              ],
            ],
            'text' => [
              'captcha_wall' => [
                'tab_title' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_tab_title'), \ENT_QUOTES),
                'title' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_title'), \ENT_QUOTES),
                'subtitle' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_subtitle'), \ENT_QUOTES),
                'refresh_image_link' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_refresh_image_link'), \ENT_QUOTES),
                'captcha_placeholder' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_captcha_placeholder'), \ENT_QUOTES),
                'send_button' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_send_button'), \ENT_QUOTES),
                'error_message' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_error_message'), \ENT_QUOTES),
                'footer' => htmlspecialchars_decode($this->getStringSettings('theme_text_captcha_wall_footer'), \ENT_QUOTES),
              ],
            ],
            'custom_css' => $this->getStringSettings('theme_custom_css'),
          ];
    }

    /**
     * @return array ['hide_crowdsec_mentions': bool, color:[text:['primary' : string, 'secondary' : string, 'error_message : string' ...]]] (returns an array of option required to build the ban wall template)
     */
    public function getBanWallOptions(): array
    {
        return [
            'hide_crowdsec_mentions' => $this->getBoolSettings('hide_mentions'),
            'color' => [
              'text' => [
                'primary' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_primary'), \ENT_QUOTES),
                'secondary' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_secondary'), \ENT_QUOTES),
                'error_message' => htmlspecialchars_decode($this->getStringSettings('theme_color_text_error_message'), \ENT_QUOTES),
              ],
              'background' => [
                'page' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_page'), \ENT_QUOTES),
                'container' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_container'), \ENT_QUOTES),
                'button' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_button'), \ENT_QUOTES),
                'button_hover' => htmlspecialchars_decode($this->getStringSettings('theme_color_background_button_hover'), \ENT_QUOTES),
              ],
            ],
            'text' => [
              'ban_wall' => [
                'tab_title' => htmlspecialchars_decode($this->getStringSettings('theme_text_ban_wall_tab_title'), \ENT_QUOTES),
                'title' => htmlspecialchars_decode($this->getStringSettings('theme_text_ban_wall_title'), \ENT_QUOTES),
                'subtitle' => htmlspecialchars_decode($this->getStringSettings('theme_text_ban_wall_subtitle'), \ENT_QUOTES),
                'footer' => htmlspecialchars_decode($this->getStringSettings('theme_text_ban_wall_footer'), \ENT_QUOTES),
              ],
            ],
            'custom_css' => htmlspecialchars_decode($this->getStringSettings('theme_custom_css'), \ENT_QUOTES),
          ];
    }

    /**
     * @return array [[string, string], ...] Returns IP ranges to trust as proxies as an array of comparables ip bounds
     */
    public function getTrustForwardedIpBoundsList(): array
    {
        return $this->getArraySettings('trust_ip_forward_array');
    }

    /**
     * Return a session variable, null if not set.
     */
    public function getSessionVariable(string $name)
    {
        return Session::getSessionVariable($name);
    }

    /**
     * Set a session variable.
     */
    public function setSessionVariable(string $name, $value): void
    {
        Session::setSessionVariable($name, $value);
    }

    /**
     * Unset a session variable, throw an error if this does not exist.
     *
     * @return void;
     */
    public function unsetSessionVariable(string $name): void
    {
        Session::unsetSessionVariable($name);
    }

    /**
     * Get the value of a posted field.
     */
    public function getPostedVariable(string $name): ?string
    {
        if (!isset($_POST[$name])) {
            return null;
        }

        return $_POST[$name];
    }

    /**
     * If the current IP should be bounced or not, matching custom business rules.
     *
     * @throws CacheException
     * @throws ErrorException
     */
    public function shouldBounceCurrentIp(): bool
    {
        // Don't bounce favicon calls
        if ('/favicon.ico' === $_SERVER['REQUEST_URI']) {
            return false;
        }

        $bouncingDisabled = (Constants::BOUNCING_LEVEL_DISABLED === $this->getStringSettings('bouncing_level'));
        if ($bouncingDisabled) {
            $this->logger->debug('', [
                'type' => 'SHOULD_NOT_BOUNCE',
                'message' => Constants::BOUNCING_LEVEL_DISABLED,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Send HTTP response.
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
     * If there is any technical problem while bouncing, don't block the user. Bypass bouncing and log the error.
     *
     * @throws CacheException
     * @throws ErrorException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function safelyBounce(array $configs): bool
    {
        $result = false;
        try {
            set_error_handler(function ($errno, $errstr) {
                throw new BouncerException("$errstr (Error level: $errno)");
            });
            $this->init($configs);
            $this->run();
            $result = true;
            restore_error_handler();
        } catch (Exception $e) {
            $this->logger->error('', [
                'type' => 'EXCEPTION_WHILE_BOUNCING',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if ($this->displayErrors) {
                throw $e;
            }
        } finally {
            if (\PHP_SESSION_NONE !== session_status()) {
                session_write_close();
                session_name($this->session_name);
            }
        }

        return $result;
    }
}