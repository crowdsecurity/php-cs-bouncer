<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSec\CapiClient\Watcher;
use CrowdSec\Common\Client\RequestHandler\Curl;
use CrowdSec\Common\Client\RequestHandler\FileGetContents;
use CrowdSec\Common\Logger\FileLog;
use CrowdSec\CapiClient\Watcher as CapiClient;
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Constants as RemConstants;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::clearCache
 * @covers \CrowdSecBouncer\AbstractBouncer::pruneCache
 * @covers \CrowdSecBouncer\AbstractBouncer::testCacheConnection
 *
 * @uses   \CrowdSecBouncer\AbstractBouncer::__construct
 * @uses   \CrowdSecBouncer\AbstractBouncer::capRemediationLevel
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
 */

final class AbstractCapiBouncerTest extends TestCase
{
    private const EXCLUDED_URI = '/favicon.ico';
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

    protected function setUp(): void
    {

        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();

        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);

        $bouncerConfigs = [
            'use_capi' => true,
            'ordered_remediations' => [RemConstants::REMEDIATION_BAN, RemConstants::REMEDIATION_CAPTCHA],
            'scenarios' => ['crowdsecurity/http-backdoors-attempts', 'crowdsecurity/http-bad-user-agent'],
            'user_agent_suffix' => 'testphpbouncer',
            'fs_cache_path' => $this->root->url() . '/.cache',
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'excluded_uris' => [self::EXCLUDED_URI],
            'trust_ip_forward_array' => [['005.006.007.008', '005.006.007.008']],
        ];

        $this->configs = $bouncerConfigs;
    }

    public function testConstructAndSomeMethods()
    {
        $bouncerConfigs = array_merge($this->configs, ['unexpected_config' => 'test']);
        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);
        $logger = new FileLog();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation, $logger]);

        $this->assertEquals(false, $bouncer->getConfig('stream_mode'), 'Stream mode config');
        $this->assertEquals(FileLog::class, \get_class($bouncer->getLogger()), 'Logger Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\CapiRemediation', \get_class($remediation), 'Remediation Init');
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\PhpFiles', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $this->assertEquals(Constants::BOUNCING_LEVEL_NORMAL, $bouncer->getConfig('bouncing_level'), 'Bouncing level config');

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
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
   public function testCaptchaFlow()
    {
        // Init bouncer
        $bouncerConfigs = array_merge($this->configs,[
            'forced_test_ip' => TestHelpers::BAD_IP,
        ]);

        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation], '', true,
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

        // Add a captcha decision for BAD IP
        TestHelpers::addLocalDecision($cache, TestHelpers::BAD_IP, RemConstants::REMEDIATION_CAPTCHA);

        // Step 1 : access a page should display a captcha wall
        $bouncer->bounceCurrentIp();

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
        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation], '', true,
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
     * @group ban
     *
     * @return void
     *
     * @throws BouncerException
     * @throws \CrowdSec\RemediationEngine\CacheStorage\CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testBanFlow()
    {
        // Init bouncer
        $bouncerConfigs = array_merge($this->configs,[
            'forced_test_ip' => TestHelpers::BAD_IP,
        ]);

        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);

        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation], '', true,
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

        // Add a ban
        TestHelpers::addLocalDecision($cache, TestHelpers::BAD_IP, RemConstants::REMEDIATION_BAN);

        $bouncer->bounceCurrentIp();

        $item = $cache->getItem($cacheKey);
        $this->assertEquals(
            true,
            $item->isHit(),
            'The remediation should be cached'
        );
    }

    /**
     *
     * @return void
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     */
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
        $client = new CapiClient($this->configs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($this->configs, $this->logger);
        $capiRemediation = new CapiRemediation($this->configs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$this->configs, $capiRemediation,
            $this->logger],
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
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce excluded uri');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Will not bounce as URI is excluded/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 3: bouncing URI
        $client = new CapiClient($this->configs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($this->configs, $this->logger);
        $capiRemediation = new CapiRemediation($this->configs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$this->configs, $capiRemediation,
            $this->logger],
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

        // Test 4:  not bouncing URI if disabled
        $bouncerConfigs = array_merge($this->configs, ['bouncing_level' => Constants::BOUNCING_LEVEL_DISABLED]);
        $client = new CapiClient($bouncerConfigs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation,
            $this->logger],
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

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Will not bounce as bouncing is disabled/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 7 : no-forward
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'forced_test_forwarded_ip' => Constants::X_FORWARDED_DISABLED,
            ]
        );
        $client = new CapiClient($bouncerConfigs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation,
            $this->logger],
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

        // Test 8 : forced X-Forwarded-for usage
        $bouncerConfigs = array_merge(
            $this->configs,
            [
                'forced_test_forwarded_ip' => '1.2.3.5',
            ]
        );
        $client = new CapiClient($bouncerConfigs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation,
            $this->logger],
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

        // Test 9 non-authorized
        $bouncerConfigs = $this->configs;
        $client = new CapiClient($bouncerConfigs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation,
            $this->logger],
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

        // Test 10 authorized
        $bouncerConfigs = $this->configs;
        $client = new CapiClient($bouncerConfigs, new FileStorage(), null, $this->logger);
        $cache = new PhpFiles($bouncerConfigs, $this->logger);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache, $this->logger);
        // Mock sendResponse and redirectResponse to avoid PHP UNIT header already sent or exit error
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation,
            $this->logger],
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
    }

    public function testPrivateAndProtectedMethods()
    {
        // handleCache
        $configs = array_merge($this->configs, ['cache_system' => 'redis']);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
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
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
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
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
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
            $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
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
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleClient',
            [$configs, new FileLog()]
        );

        $this->assertEquals('CrowdSec\CapiClient\Client\CapiHandler\FileGetContents', \get_class($result->getRequestHandler()));

        $configs = array_merge($this->configs, ['use_curl' => true]);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleClient',
            [$configs, new FileLog()]
        );

        $this->assertEquals('CrowdSec\CapiClient\Client\CapiHandler\Curl', \get_class($result->getRequestHandler()));
    }



    /**
     * @group integration
     */
    public function testCanVerifyIp(): void
    {

        // Init bouncer
        $bouncerConfigs = $this->configs;

        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation]);
        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        // Add a ban
        TestHelpers::addLocalDecision($cache, TestHelpers::BAD_IP, RemConstants::REMEDIATION_BAN);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $client = new Watcher($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation]);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation]);
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP),
            'Get decisions for a clean IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Preload the remediation to prepare the next tests.
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP),
            'Preload the bypass remediation to prepare the next tests'
        );

        // Remove old decisions and add a ban
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        TestHelpers::addLocalDecision($cache, TestHelpers::NEWLY_BAD_IP, RemConstants::REMEDIATION_BAN);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP),
            'The new decision should now be added, so the previously clean IP should now be bad'
        );

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'The old decisions should now be removed, so the previously bad IP should now be clean'
        );

        // Set up a new instance.
        $client = new CapiClient($bouncerConfigs, new FileStorage());
        $cache = new PhpFiles($bouncerConfigs);
        $capiRemediation = new CapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $capiRemediation]);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP)
        );

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" + "IPV6 range banned" state']);

        // Remove old decisions and add a range ban
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        TestHelpers::addLocalDecision($cache, TestHelpers::BAD_IP . '/' . TestHelpers::LARGE_IPV4_RANGE ,
            RemConstants::REMEDIATION_BAN, RemConstants::SCOPE_RANGE);
        TestHelpers::addLocalDecision($cache, TestHelpers::BAD_IPV6 . '/' . TestHelpers::IPV6_RANGE ,
            RemConstants::REMEDIATION_BAN, RemConstants::SCOPE_RANGE);

        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals(
            'ban',
            $cappedRemediation,
            'The remediation for the banned IP with a large range should be "ban" even in stream mode'
        );
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals(
            'bypass',
            $cappedRemediation,
            'The remediation for the banned IPV6 with a too large range should now be "bypass" as we are in stream mode'
        );

        // Test cache connection
        $bouncer->testCacheConnection();
    }
}
