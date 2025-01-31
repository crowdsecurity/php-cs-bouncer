<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSec\Common\Client\RequestHandler\Curl;
use CrowdSec\Common\Client\RequestHandler\FileGetContents;
use CrowdSec\Common\Logger\ConsoleLog;
use CrowdSec\Common\Logger\FileLog;
use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::clearCache
 * @covers \CrowdSecBouncer\AbstractBouncer::pruneCache
 * @covers \CrowdSecBouncer\AbstractBouncer::testCacheConnection
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediation
 * @covers \CrowdSecBouncer\AbstractBouncer::getAppSecRemediationForIp
 * @covers \CrowdSecBouncer\AbstractBouncer::getAppSecHeaders
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldUseAppSec
 *
 * @uses   \CrowdSecBouncer\AbstractBouncer::__construct
 * @uses   \CrowdSecBouncer\AbstractBouncer::configure
 * @uses   \CrowdSecBouncer\AbstractBouncer::getConfig
 * @uses   \CrowdSecBouncer\AbstractBouncer::getConfigs
 * @uses   \CrowdSecBouncer\AbstractBouncer::getLogger
 * @uses   \CrowdSecBouncer\AbstractBouncer::getRemediationEngine
 *
 * @covers   \CrowdSecBouncer\AbstractBouncer::handleCache
 * @covers   \CrowdSecBouncer\AbstractBouncer::handleClient
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshBlocklistCache
 *
 * @uses   \CrowdSecBouncer\Configuration::addBouncerNodes
 * @uses   \CrowdSecBouncer\Configuration::addCacheNodes
 * @uses   \CrowdSecBouncer\Configuration::addConnectionNodes
 * @uses   \CrowdSecBouncer\Configuration::addDebugNodes
 * @uses   \CrowdSecBouncer\Configuration::addTemplateNodes
 * @uses   \CrowdSecBouncer\Configuration::cleanConfigs
 * @uses   \CrowdSecBouncer\Configuration::getConfigTreeBuilder
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::bounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::getTrustForwardedIpBoundsList
 * @covers \CrowdSecBouncer\AbstractBouncer::handleForwardedFor
 * @covers \CrowdSecBouncer\AbstractBouncer::handleRemediation
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldTrustXforwardedFor
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::getBanHtml
 * @uses \CrowdSecBouncer\AbstractBouncer::handleBanRemediation
 * @uses \CrowdSecBouncer\AbstractBouncer::sendResponse
 * @uses \CrowdSecBouncer\Template::__construct
 * @uses \CrowdSecBouncer\Template::render
 * @uses \CrowdSecBouncer\AbstractBouncer::buildCaptchaCouple
 * @uses \CrowdSecBouncer\AbstractBouncer::displayCaptchaWall
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::getCache
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::getCaptchaHtml
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCaptchaRemediation
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCaptchaResolutionForm
 * @covers \CrowdSecBouncer\AbstractBouncer::initCaptchaResolution
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldNotCheckResolution
 * @covers \CrowdSecBouncer\AbstractBouncer::checkCaptcha
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshCaptcha
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationForIp
 * @covers \CrowdSecBouncer\AbstractBouncer::run
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldBounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::handleBounceExclusion
 * @covers \CrowdSecBouncer\AbstractBouncer::pushUsageMetrics
 */
final class AbstractBouncerTest extends TestCase
{
    private const EXCLUDED_URI = '/favicon.ico';
    /** @var WatcherClient */
    private $watcherClient;

    /** @var bool */
    private $useTls;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var string
     */
    private $prodFile;
    /**
     * @var vfsStreamDirectory
     */
    private $root;
    /**
     * @var array
     */
    private $configs;

    public const BOUNCER_NAME = 'bouncer-lib-integration-test';
    public const BOUNCER_VERSION = 'v0.0.0';
    public const BOUNCER_TYPE = 'crowdsec-test-php-bouncer';

    protected function setUp(): void
    {
        $this->useTls = (string) getenv('BOUNCER_TLS_PATH');

        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();

        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true, 'log_rotator' => true]);

        $bouncerConfigs = [
            'auth_type' => $this->useTls ? \CrowdSec\LapiClient\Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => getenv('BOUNCER_KEY'),
            'api_url' => getenv('LAPI_URL'),
            'appsec_url' => getenv('APPSEC_URL'),
            'user_agent_suffix' => 'testphpbouncer',
            'fs_cache_path' => $this->root->url() . '/.cache',
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'excluded_uris' => [self::EXCLUDED_URI],
            'stream_mode' => false,
            'trust_ip_forward_array' => [['005.006.007.008', '005.006.007.008']],
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $this->configs = $bouncerConfigs;
        $this->watcherClient = new WatcherClient($this->configs);
        // Delete all decisions
        $this->watcherClient->deleteAllDecisions();
    }

    private function addTlsConfig(&$bouncerConfigs, $tlsPath)
    {
        $bouncerConfigs['tls_cert_path'] = $tlsPath . '/bouncer.pem';
        $bouncerConfigs['tls_key_path'] = $tlsPath . '/bouncer-key.pem';
        $bouncerConfigs['tls_ca_cert_path'] = $tlsPath . '/ca-chain.pem';
        $bouncerConfigs['tls_verify_peer'] = true;
    }

    public function testConstructAndSomeMethods()
    {
        $bouncerConfigs = array_merge($this->configs, ['unexpected_config' => 'test']);
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);
        $logger = new FileLog();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $logger]);

        $this->assertEquals(false, $bouncer->getConfig('stream_mode'), 'Stream mode config');
        $this->assertEquals(FileLog::class, \get_class($bouncer->getLogger()), 'Logger Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\LapiRemediation', \get_class($remediation), 'Remediation Init');
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\PhpFiles', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $this->assertEquals(Constants::BOUNCING_LEVEL_NORMAL, $remediation->getConfig('bouncing_level'), 'Bouncing level config');

        $this->assertEquals(null, $bouncer->getConfig('unexpected_config'), 'Should clean config');

        $configs = $bouncer->getConfigs();
        $this->assertArrayHasKey('text', $configs, 'Config should have text key');
        $this->assertArrayHasKey('color', $configs, 'Config should have color key');
    }

    /**
     * @group captcha
     *
     * @return void
     *
     * @throws BouncerException
     * @throws \CrowdSec\RemediationEngine\CacheStorage\CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCaptchaFlow()
    {
        $this->watcherClient->setSimpleDecision('captcha');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => false,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation], '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
            ]);

        $bouncer->clearCache();

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The captcha variables should not be cached'
        );

        // Step 1 : access a page should display a captcha wall
        $bouncer->bounceCurrentIp();
        usleep(200 * 1000); // wait for cache to be written

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The captcha variables should be cached'
        );

        $this->assertEquals(
            true,
            $item->isHit(),
            'The captcha variables should be cached'
        );

        $cached = $item->get();
        $this->assertEquals(
            true,
            $cached['has_to_be_resolved'],
            'The captcha variables should be cached'
        );
        $phraseToGuess = $cached['phrase_to_guess'];
        $this->assertEquals(
            5,
            strlen($phraseToGuess),
            'The captcha variables should be cached'
        );
        $this->assertEquals(
            '/',
            $cached['resolution_redirect'],
            'The captcha variables should be cached'
        );
        $this->assertNotEmpty($cached['inline_image'],
            'The captcha variables should be cached');

        $this->assertEquals(
            false,
            $cached['resolution_failed'],
            'The captcha variables should be cached'
        );

        // Step 2 :refresh
        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST', 'POST', 'POST', 'POST');
        $bouncer->method('getPostedVariable')->willReturnOnConsecutiveCalls('1', '1', '1', '1', '1', 'bad-phrase', 'bad-phrase');

        $_SERVER['HTTP_REFERER'] = 'UNIT-TEST';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['refresh'] = '1';
        $_POST['crowdsec_captcha'] = '1';
        $bouncer->bounceCurrentIp();
        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();
        $phraseToGuess2 = $cached['phrase_to_guess'];
        $this->assertNotEquals(
            $phraseToGuess2,
            $phraseToGuess,
            'Phrase should have been refresh'
        );
        $this->assertEquals(
            '/',
            $cached['resolution_redirect'],
            'Referer is only for the first step if post'
        );

        // STEP 3 : resolve captcha but failed
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['refresh']);
        $_POST['phrase'] = 'bad-phrase';
        $_POST['crowdsec_captcha'] = '1';
        $bouncer->bounceCurrentIp();

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();

        $this->assertEquals(
            true,
            $cached['resolution_failed'],
            'Failed should be cached'
        );

        // STEP 4 : resolve captcha success
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation], '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
            ]);

        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST');
        $bouncer->method('getPostedVariable')->willReturnOnConsecutiveCalls('1', null, '1', $phraseToGuess2);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['phrase'] = $phraseToGuess2;
        $_POST['crowdsec_captcha'] = '1';

        $bouncer->bounceCurrentIp();

        $cacheKeyCaptcha = $cache->getCacheKey(Constants::CACHE_TAG_CAPTCHA, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKeyCaptcha);
        $cached = $item->get();

        $this->assertEquals(
            false,
            $cached['has_to_be_resolved'],
            'Resolved should be cached'
        );
    }

    /**
     * @group appsec
     *
     * @return void
     *
     * @throws BouncerException
     * @throws \CrowdSec\RemediationEngine\CacheStorage\CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testAppSecFlow()
    {
        $this->watcherClient->setSimpleDecision('ban');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'appsec_url' => TestHelpers::getAppSecUrl(),
            'use_appsec' => true,
            'stream_mode' => false,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation], '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRequestHeaders',
                'getRequestUri',
                'getRequestHost',
                'getRequestUserAgent',
                'getRequestRawBody',
            ]);

        // On test 1, these methods won't be called because LAPI returns a ban
        // On test 2, these methods will be called (malicious POST request)
        // On test 3, these methods will be called (clean request)
        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/login', '/home');
        $bouncer->method('getRequestHost')->willReturnOnConsecutiveCalls('example.com', 'example.com');
        $bouncer->method('getRequestUserAgent')->willReturnOnConsecutiveCalls('Mozilla/5.0', 'Mozilla/5.0');
        $bouncer->method('getRequestHeaders')->willReturnOnConsecutiveCalls(['Content-Type' => 'application/x-www-form-urlencoded'], ['Content-Type' => 'application/x-www-form-urlencoded']);
        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST', 'GET');
        $bouncer->method('getRequestRawBody')->willReturnOnConsecutiveCalls('class.module.classLoader.resources.', '');

        $bouncer->clearCache();

        // TEST 1 : ban from LAPI

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );
        $cachedItem = $item->get();
        $this->assertEquals(
            'ban',
            $cachedItem[0][0],
            'The remediation should be ban'
        );

        // Test 2: ban from APP SEC
        $this->watcherClient->deleteAllDecisions();
        $bouncer->clearCache();

        $bouncer->bounceCurrentIp();
        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $cachedItem = $item->get();
        $this->assertEquals(
            'bypass',
            $cachedItem[0][0],
            'The LAPI remediation should be bypass and has been stored'
        );

        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        if ($this->useTls) {
            $this->assertArrayNotHasKey('appsec', $originCountItem, 'The origin count for appsec should not be present');
            $this->assertEquals(
                1,
                $originCountItem['clean']['bypass'],
                'The origin count for clean should be 1'
            );
        } else {
            $this->assertEquals(
                1,
                $originCountItem['appsec']['ban'],
                'The origin count for appsec should be 1'
            );
        }

        // Test 3: clean IP and clean request
        $bouncer->clearCache();
        $bouncer->bounceCurrentIp();
        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $cachedItem = $item->get();
        $this->assertEquals(
            'bypass',
            $cachedItem[0][0],
            'The LAPI remediation should be bypass and has been stored'
        );

        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        if ($this->useTls) {
            $this->assertArrayNotHasKey('appsec', $originCountItem, 'The origin count for appsec should not be present');
            $this->assertEquals(
                1,
                $originCountItem['clean']['bypass'],
                'The origin count for clean should be 1'
            );
        } else {
            $this->assertEquals(
                1,
                $originCountItem['clean_appsec']['bypass'],
                'The origin count for appsec should be 1'
            );
        }
    }

    public function testBanFlow()
    {
        $this->watcherClient->setSimpleDecision('ban');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => false,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation], '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
            ]);

        $bouncer->clearCache();

        $cache = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheKey = $cache->getCacheKey(Constants::SCOPE_IP, TestHelpers::BAD_IP);
        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            false,
            $item->isHit(),
            'The remediation should not be cached'
        );

        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );

        // Test Push metrics
        $result = $bouncer->pushUsageMetrics(self::BOUNCER_NAME, self::BOUNCER_VERSION, self::BOUNCER_TYPE);

        $items = $result['remediation_components'][0]['metrics'][0]['items'];
        $droppedCount = 0;
        foreach ($items as $item) {
            if ('processed' === $item['name']) {
                $this->assertEquals(1, $item['value'], 'The processed value should be 1');
            }
            if ('dropped' === $item['name']) {
                $droppedCount += $item['value'];
            }
        }
        $this->assertEquals(1, $droppedCount, 'The dropped count should be 1. Result was: ' . json_encode($result));
    }

    /**
     * This test requires to have some App Sec rules installed in the Crowdsec server:
     * /etc/crowdsec/appsec-rules/vpatch-CVE-2022-22965.yaml (POST with class.module.classLoader.resources. body)
     * and crowdsecurity/appsec-generic-rules (GET /.env file))
     *
     * @group appsec
     */
    public function testAppSecRemediation()
    {
        if (empty($this->configs['appsec_url'])) {
            $this->fail('There must be an App Sec Url defined with APPSEC_URL env');
        }

        $client = new BouncerClient($this->configs, null, $this->logger);
        $cache = new PhpFiles($this->configs, $this->logger);
        $lapiRemediation = new LapiRemediation($this->configs, $client, $cache, $this->logger);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$this->configs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'getHttpMethod',
                'getRequestHeaders',
                'getRequestUri',
                'getRequestHost',
                'getRequestUserAgent',
                'getRequestRawBody',
            ]);
        $ip = '1.2.3.4';
        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/login', '/login', '/.env', 'home.php');
        $bouncer->method('getRequestHost')->willReturnOnConsecutiveCalls('example.com', 'example.com', 'example.com', 'example.com');
        $bouncer->method('getRequestUserAgent')->willReturnOnConsecutiveCalls('Mozilla/5.0', 'Mozilla/5.0', 'Mozilla/5.0', 'Method...');
        $bouncer->method('getRequestHeaders')->willReturnOnConsecutiveCalls(['Content-Type' => 'application/x-www-form-urlencoded'], ['Content-Type' => 'application/x-www-form-urlencoded'], [], []);
        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST', 'POST', 'GET', 'GET');
        $bouncer->method('getRequestRawBody')->willReturnOnConsecutiveCalls('class.module.classLoader.resources.', 'admin=test', '', '');

        // Test 1: POST request with malicious body
        $this->assertEquals('ban', $bouncer->getAppSecRemediationForIp($ip)['remediation'], 'Should get a ban remediation');
        // Test 2: POST request with normal body
        $this->assertEquals('bypass', $bouncer->getAppSecRemediationForIp($ip)['remediation'], 'Should get a ban remediation');
        // Test 3: GET request with malicious behavior
        $this->assertEquals('ban', $bouncer->getAppSecRemediationForIp($ip)['remediation'], 'Should get a ban remediation');
        // Test 4: GET request with clean behavior
        $this->assertEquals('bypass', $bouncer->getAppSecRemediationForIp($ip)['remediation'], 'Should get a ban remediation');

        // Test 5: 401 because of bad API-KEY
        $bouncerConfigs = array_merge($this->configs, ['api_key' => 'bad-key']);
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'getHttpMethod',
                'getRequestHeaders',
                'getRequestUri',
                'getRequestHost',
                'getRequestUserAgent',
                'getRequestRawBody',
            ]);
        // Same mock as for the first test (malicious POST request)
        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/login');
        $bouncer->method('getRequestHost')->willReturnOnConsecutiveCalls('example.com');
        $bouncer->method('getRequestUserAgent')->willReturnOnConsecutiveCalls('Mozilla/5.0');
        $bouncer->method('getRequestHeaders')->willReturnOnConsecutiveCalls(['Content-Type' => 'application/x-www-form-urlencoded']);
        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST');
        $bouncer->method('getRequestRawBody')->willReturnOnConsecutiveCalls('class.module.classLoader.resources.');

        $error = '';
        try {
            $bouncer->getAppSecRemediationForIp($ip, $bouncer->getRemediationEngine());
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }
        $this->assertEquals('Unexpected response status code: 401. Body was: ', $error, 'Should get a 401 error');
    }

    public function testRun()
    {
        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should not exist'
        );
        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->debugFile),
            'Debug File should not exist'
        );

        // Test 2: not bouncing exclude URI
        $client = new BouncerClient($this->configs, null, $this->logger);
        $cache = new PhpFiles($this->configs, $this->logger);
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            null,
            $originCountItem,
            'The origin count for clean should be empty'
        );
        $lapiRemediation = new LapiRemediation($this->configs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$this->configs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls(self::EXCLUDED_URI);
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.2');
        $this->assertEquals(false, $bouncer->run(), 'Should not run as URI is excluded from bouncing');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*This URI is excluded from bouncing/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            null,
            $originCountItem,
            'The origin count for clean should be null'
        );

        // Test 3: bouncing URI
        $client = new BouncerClient($this->configs, null, $this->logger);
        $cache = new PhpFiles($this->configs, $this->logger);
        $lapiRemediation = new LapiRemediation($this->configs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$this->configs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.3');
        $this->assertEquals(true, $bouncer->run(), 'Should bounce uri');
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 1]],
            $originCountItem,
            'The origin count for clean should be 1'
        );

        // Test 4:  not bouncing URI if disabled
        $bouncerConfigs = $this->configs;
        $remediationConfigs = array_merge($this->configs, ['bouncing_level' => Constants::BOUNCING_LEVEL_DISABLED]);
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($remediationConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.4');
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce if disabled');
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 1]],
            $originCountItem,
            'The origin count for clean should still be 1 as we did not bounce at all due to config'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Bouncing is disabled by bouncing_level configuration/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 5: throw error if config says so
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'display_errors' => true,
                'api_url' => 'bad-url',
            ]
        );
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.5');

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        $errorExpected = '/Could not resolve host/';
        PHPUnitUtil::assertRegExp(
            $this,
            $errorExpected,
            $error,
            'Should have throw an error'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*EXCEPTION_WHILE_BOUNCING/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 1]],
            $originCountItem,
            'The origin count for clean should be still 1'
        );

        // Test 6: NOT throw error if config says so
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'display_errors' => false,
                'api_url' => 'bad-url',
            ]
        );
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.6');

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 1]],
            $originCountItem,
            'The origin count for clean should be still 1'
        );

        $this->assertEquals('', $error, 'Should not throw error');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*EXCEPTION_WHILE_BOUNCING/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );

        // Test 7 : no-forward
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'forced_test_forwarded_ip' => Constants::X_FORWARDED_DISABLED,
            ]
        );
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.7');

        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is disabled/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 2]],
            $originCountItem,
            'The origin count for clean should be 2'
        );

        // Test 8 : forced X-Forwarded-for usage
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'forced_test_forwarded_ip' => '1.2.3.5',
            ]
        );
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.8');

        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is forced.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 3]],
            $originCountItem,
            'The origin count for clean should be 3'
        );

        // Test 9 non-authorized
        $bouncerConfigs = $this->configs;
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('127.0.0.9');
        $bouncer->method('getHttpRequestHeader')->willReturnOnConsecutiveCalls('1.2.3.5'); // HTTP_X_FORWARDED_FOR

        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*Detected IP is not allowed for X-Forwarded-for usage.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 4]],
            $originCountItem,
            'The origin count for clean should be 4'
        );

        // Test 10 authorized
        $bouncerConfigs = $this->configs;
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation, $this->logger],
            '', true,
            true, true, [
                'sendResponse',
                'redirectResponse',
                'getHttpMethod',
                'getPostedVariable',
                'getHttpRequestHeader',
                'getRemoteIp',
                'getRequestUri',
            ]);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls('/home');
        $bouncer->method('getRemoteIp')->willReturnOnConsecutiveCalls('5.6.7.8');
        $bouncer->method('getHttpRequestHeader')->willReturnOnConsecutiveCalls('127.0.0.10'); // HTTP_X_FORWARDED_FOR
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Detected IP is allowed for X-Forwarded-for usage.*"original_ip":"5.6.7.8","x_forwarded_for_ip":"127.0.0.10"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 5]],
            $originCountItem,
            'The origin count for clean should be 5'
        );
        // Test 11: push metrics
        $result = $bouncer->pushUsageMetrics(self::BOUNCER_NAME, self::BOUNCER_VERSION, self::BOUNCER_TYPE);
        $this->assertEquals(
            [
                'name' => 'processed',
                'value' => 5,
                'unit' => 'request',
            ],
            $result['remediation_components'][0]['metrics'][0]['items'][0],
            'Should have pushed 5 processed metrics'
        );
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            ['clean' => ['bypass' => 0]],
            $originCountItem,
            'The origin count for clean should be reset'
        );
    }

    public function testPrivateAndProtectedMethods()
    {
        // handleCache
        $configs = array_merge($this->configs, ['cache_system' => 'redis']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleCache',
            [$configs, new FileLog()]
        );

        $this->assertInstanceOf(Redis::class, $result);

        $configs = array_merge($this->configs, ['cache_system' => 'memcached']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleCache',
            [$configs, new FileLog()]
        );

        $this->assertInstanceOf(Memcached::class, $result);

        $configs = array_merge($this->configs, ['cache_system' => 'phpfs']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleCache',
            [$configs, new FileLog()]
        );

        $this->assertInstanceOf(PhpFiles::class, $result);

        $error = '';

        try {
            $configs = array_merge($this->configs, ['cache_system' => 'phpfs']);
            $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
                ->getMock();
            $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

            PHPUnitUtil::callMethod(
                $bouncer,
                'handleCache',
                [array_merge($configs, ['cache_system' => 'bad-cache-name']), new FileLog()]
            );
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/Unknown selected cache technology: bad-cache-name/',
            $error,
            'Should have throw an error'
        );

        // handleClient
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleClient',
            [$configs, new FileLog()]
        );

        $this->assertEquals(FileGetContents::class, \get_class($result->getRequestHandler()));

        $configs = array_merge($this->configs, ['use_curl' => true]);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleClient',
            [$configs, new FileLog()]
        );

        $this->assertEquals(Curl::class, \get_class($result->getRequestHandler()));
    }

    /**
     * For some reason, the origin count can be cscli or crowdsec, so we can not test origin count directly.
     *
     * @param array $expected
     *                        $expected[0] = ban count
     *                        $expected[1] = captcha count
     *                        $expected[2] = bypass count
     *
     * @throws InvalidArgumentException
     */
    private function checkCounts($cache, array $expected): void
    {
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();

        $banCount = 0;
        $captchaCount = 0;
        $bypassCount = 0;
        foreach ($originCountItem as $origin => $counts) {
            foreach ($counts as $remediation => $value) {
                if ('ban' === $remediation) {
                    $banCount += $value;
                }
                if ('captcha' === $remediation) {
                    $captchaCount += $value;
                }
                if ('bypass' === $remediation) {
                    $bypassCount += $value;
                }
            }
        }

        $this->assertEquals(
            $expected[0],
            $banCount,
            'The ban count should be ok'
        );
        $this->assertEquals(
            $expected[1],
            $captchaCount,
            'The captcha count should be ok'
        );
        $this->assertEquals(
            $expected[2],
            $bypassCount,
            'The bypass count should be ok'
        );
    }

    /**
     * @group integration
     */
    public function testCanVerifyIpInLiveMode(): void
    {
        // Init context
        $this->watcherClient->setInitialState();

        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
            'stream_mode' => false,
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);

        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            null,
            $originCountItem,
            'The origin count for clean should be empty'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Get decisions for a bad IP (for the first time, it should be a cache miss)'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Call the same thing for the second time (now it should be a cache hit)'
        );

        $cleanRemediation1stCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP);
        $this->assertEquals(
            'bypass',
            $cleanRemediation1stCall['remediation'],
            'Get decisions for a clean IP for the first time (it should be a cache miss)'
        );

        // Call the same thing for the second time (now it should be a cache hit)
        $cleanRemediation2ndCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP);
        $this->assertEquals('bypass', $cleanRemediation2ndCall['remediation']);

        // Clear cache
        $this->assertTrue($bouncer->clearCache(), 'The cache should be clearable');
        $originCountItem = $cache->getItem(AbstractCache::ORIGINS_COUNT)->get();
        $this->assertEquals(
            null,
            $originCountItem,
            'The origin count should be ok'
        );

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('ban', $remediation3rdCall['remediation']);

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $remediationConfigs = array_merge($bouncerConfigs, ['bouncing_level' => Constants::BOUNCING_LEVEL_FLEX]);
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($remediationConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation['remediation'], 'The remediation for the banned IP should now be "captcha"');
        // Reset the max remediation level to its origin state
        $remediationConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($remediationConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IP . '/' . TestHelpers::LARGE_IPV4_RANGE,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals(
            'ban',
            $cappedRemediation['remediation'],
            'The remediation for the banned IPv4 range should be ban'
        );

        $this->logger->info('', ['message' => 'set "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6 . '/' . TestHelpers::IPV6_RANGE,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals(
            'ban',
            $cappedRemediation['remediation'],
            'The remediation for a banned IPv6 range should be ban in live mode'
        );
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6,
            'ban'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals(
            'ban',
            $cappedRemediation['remediation'],
            'The remediation for a banned IPv6 should be ban'
        );
    }

    /**
     * @group integration
     * @group stream
     */
    public function testCanVerifyIpInStreamMode(): void
    {
        // Uncomment the below line to see debug log in console
        // $this->logger = new ConsoleLog();
        // Init context
        $this->watcherClient->setInitialState();
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        // As we are in stream mode, no live call should be done to the API.
        // Warm BlockList cache up

        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $remediationConfigs = array_merge($bouncerConfigs, ['bouncing_level' => Constants::BOUNCING_LEVEL_FLEX]);
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($remediationConfigs, $client, $cache, $this->logger);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation['remediation'], 'The remediation for the banned IP should now be "captcha"');
        $remediationConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($remediationConfigs, $client, $cache, $this->logger);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP)['remediation'],
            'Get decisions for a clean IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Preload the remediation to prepare the next tests.
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation'],
            'Preload the bypass remediation to prepare the next tests'
        );
        // Add and remove decision
        $this->watcherClient->setSecondState();
        // Pull updates
        $bouncer->refreshBlocklistCache();
        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation'],
            'The new decision should now be added, so the previously clean IP should now be bad'
        );
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP)['remediation'],
            'The old decisions should now be removed, so the previously bad IP should now be clean'
        );

        // Set up a new instance.
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
        ];
        if ($this->useTls) {
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;
        }

        $client = new BouncerClient($bouncerConfigs, null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)['remediation']
        );

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" + "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IP . '/' . TestHelpers::LARGE_IPV4_RANGE,
            'ban'
        );
        $this->watcherClient->addDecision(
            new \DateTime(),
            '24h',
            WatcherClient::HOURS24,
            TestHelpers::BAD_IPV6 . '/' . TestHelpers::IPV6_RANGE,
            'ban'
        );
        // Pull updates
        $bouncer->refreshBlocklistCache();

        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals(
            'ban',
            $cappedRemediation['remediation'],
            'The remediation for the banned IP with a large range should be "ban" even in stream mode'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals(
            'bypass',
            $cappedRemediation['remediation'],
            'The remediation for the banned IPV6 with a too large range should now be "bypass" as we are in stream mode'
        );

        // Test cache connection
        $bouncer->testCacheConnection();
    }
}
