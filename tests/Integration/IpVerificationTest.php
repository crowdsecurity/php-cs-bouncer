<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\RemediationEngine\CacheStorage\CacheStorageException;
use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\StandaloneBouncer;
use CrowdSecBouncer\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \CrowdSecBouncer\StandaloneBouncer::getRemediationForIp
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
 * @covers   \CrowdSecBouncer\AbstractBouncer::handleCache
 * @uses   \CrowdSecBouncer\AbstractBouncer::handleClient
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshBlocklistCache
 * @uses   \CrowdSecBouncer\Configuration::addBouncerNodes
 * @uses   \CrowdSecBouncer\Configuration::addCacheNodes
 * @uses   \CrowdSecBouncer\Configuration::addConnectionNodes
 * @uses   \CrowdSecBouncer\Configuration::addDebugNodes
 * @uses   \CrowdSecBouncer\Configuration::addTemplateNodes
 * @uses   \CrowdSecBouncer\Configuration::cleanConfigs
 * @uses   \CrowdSecBouncer\Configuration::getConfigTreeBuilder
 * @uses   \CrowdSecBouncer\StandaloneBouncer::__construct
 * @uses   \CrowdSecBouncer\StandaloneBouncer::handleTrustedIpsConfig
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::bounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::getTrustForwardedIpBoundsList
 * @covers \CrowdSecBouncer\AbstractBouncer::handleForwardedFor
 * @covers \CrowdSecBouncer\AbstractBouncer::handleRemediation
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldTrustXforwardedFor
 * @covers \CrowdSecBouncer\StandaloneBouncer::getHttpRequestHeader
 * @covers \CrowdSecBouncer\StandaloneBouncer::getRemoteIp
 * @covers \CrowdSecBouncer\StandaloneBouncer::run
 * @covers \CrowdSecBouncer\StandaloneBouncer::shouldBounceCurrentIp
 * @covers \CrowdSecBouncer\StandaloneBouncer::getHttpMethod
 * @covers \CrowdSecBouncer\StandaloneBouncer::getPostedVariable
 *
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::getBanHtml
 * @uses \CrowdSecBouncer\AbstractBouncer::handleBanRemediation
 * @uses \CrowdSecBouncer\AbstractBouncer::sendResponse
 * @uses \CrowdSecBouncer\Template::__construct
 * @uses \CrowdSecBouncer\Template::render
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::buildCaptchaCouple
 * @uses \CrowdSecBouncer\AbstractBouncer::displayCaptchaWall
 * @covers \CrowdSecBouncer\AbstractBouncer::getCache
 * @uses \CrowdSecBouncer\AbstractBouncer::getCaptchaHtml
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCaptchaRemediation
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCaptchaResolutionForm
 * @covers \CrowdSecBouncer\AbstractBouncer::initCaptchaResolution
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldNotCheckResolution
 * @covers \CrowdSecBouncer\AbstractBouncer::checkCaptcha
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshCaptcha
 * @covers \CrowdSecBouncer\StandaloneBouncer::getRequestUri
 *
 *
 *
 */
final class IpVerificationTest extends TestCase
{

    private const EXCLUDED_URI = '/favicon.ico';
    /** @var WatcherClient */
    private $watcherClient;

    /** @var bool */
    private $useCurl;

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

    protected function setUp(): void
    {
        $this->useTls = (string)getenv('BOUNCER_TLS_PATH');
        $this->useCurl = (bool)getenv('USE_CURL');

        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();

        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);

        $bouncerConfigs = [
            'auth_type' => $this->useTls ? \CrowdSec\LapiClient\Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => getenv('BOUNCER_KEY'),
            'api_url' => getenv('LAPI_URL'),
            'use_curl' => $this->useCurl,
            'user_agent_suffix' => 'testphpbouncer',
            'fs_cache_path' => $this->root->url() . '/.cache',
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'excluded_uris' => [self::EXCLUDED_URI],
            'stream_mode' => false,
            'trust_ip_forward_array' => ['5.6.7.8']
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $this->configs = $bouncerConfigs;
        $this->watcherClient = new WatcherClient($this->configs);
        // Delete all decisions
        $this->watcherClient->deleteAllDecisions();
    }

    public function cacheAdapterConfigProvider(): array
    {
        return TestHelpers::cacheAdapterConfigProvider();
    }

    private function cacheAdapterCheck($cacheAdapter, $origCacheName)
    {
        switch ($origCacheName) {
            case 'PhpFilesAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\PhpFiles',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'MemcachedAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\Memcached',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'RedisAdapter':
                $this->assertEquals(
                    'CrowdSec\RemediationEngine\CacheStorage\Redis',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            default:
                break;
        }
    }

    private function addTlsConfig(&$bouncerConfigs, $tlsPath)
    {
        $bouncerConfigs['tls_cert_path'] = $tlsPath . '/bouncer.pem';
        $bouncerConfigs['tls_key_path'] = $tlsPath . '/bouncer-key.pem';
        $bouncerConfigs['tls_ca_cert_path'] = $tlsPath . '/ca-chain.pem';
        $bouncerConfigs['tls_verify_peer'] = true;
    }

    /**
     * @group integration
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testTestCacheConnexion($cacheAdapterName, $origCacheName)
    {
        $bouncer = new StandaloneBouncer(array_merge($this->configs,
                ['cache_system'=> $cacheAdapterName]));
        $error = '';
        try {
            $bouncer->testCacheConnection();
        } catch (\Exception $e){
            $error = $e->getMessage();
        }
        $this->assertEquals('', $error);

        // Test custom error handler for Memcached
        if($cacheAdapterName === 'memcached'){
            $bouncer2 = new StandaloneBouncer(array_merge($this->configs,
                [
                    'cache_system'=> $cacheAdapterName,
                    'memcached_dsn' => 'memcached://memcached:21',
                ]));

            $error = '';
            try {
                $bouncer2->testCacheConnection();
            } catch (BouncerException $e){
                $error = $e->getMessage();
            }
            PHPUnitUtil::assertRegExp(
                $this,
                '/Error while testing cache connection/',
                $error,
                'Should have throw an error'
            );
        }
        // Test bad dsn for redis
        if($cacheAdapterName === 'redis'){
            $error = '';
            try {
                $bouncer3 = new StandaloneBouncer(array_merge($this->configs,
                    [
                        'cache_system'=> $cacheAdapterName,
                        'redis_dsn' => 'redis://redis:21'
                    ]));
            } catch (CacheStorageException $e){
                $error = $e->getMessage();
            }
            PHPUnitUtil::assertRegExp(
                $this,
                '/Error when creating/',
                $error,
                'Should have throw an error'
            );

        }
    }

    public function testConstructAndSomeMethods()
    {
        unset($_SERVER['REMOTE_ADDR'] );
        $bouncer = new StandaloneBouncer(array_merge($this->configs, ['unexpected_config' => 'test']));
        $this->assertEquals('', $bouncer->getRemoteIp(), 'Should return empty string');
        $_SERVER['REMOTE_ADDR'] = '5.6.7.8';
        $this->assertEquals('5.6.7.8', $bouncer->getRemoteIp(), 'Should remote ip');

        $this->assertEquals(false, $bouncer->getConfig('stream_mode'), 'Stream mode config');
        $this->assertEquals(FileLog::class, \get_class($bouncer->getLogger()), 'Logger Init');

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\LapiRemediation', \get_class($remediation), 'Remediation Init');
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\PhpFiles', \get_class($remediation->getCacheStorage()), 'Remediation cache Init');

        $error = '';

        try {
            $bouncer =
                new StandaloneBouncer(array_merge($this->configs, ['trust_ip_forward_array' => [['001.002.003.004', '001.002.003.004']]]));
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/\'trust_ip_forward_array\' config must be an array of string/',
            $error,
            'Should have throw an error'
        );

        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $bouncer->getConfig('trust_ip_forward_array'), 'Forwarded array config');

        $this->assertEquals(Constants::BOUNCING_LEVEL_NORMAL, $bouncer->getConfig('bouncing_level'), 'Bouncing level config');

        $this->assertEquals(null, $bouncer->getConfig('unexpected_config'), 'Should clean config');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('POST', $bouncer->getHttpMethod(), 'Should get HTTP method');

        $this->assertEquals(null, $bouncer->getHttpRequestHeader('X-Forwarded-For'), 'Should get HTTP header');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $this->assertEquals('1.2.3.5', $bouncer->getHttpRequestHeader('X-Forwarded-For'), 'Should get HTTP header');

        $_POST['test'] = 'test-post';
        $this->assertEquals(null, $bouncer->getPostedVariable('hello'), 'Should return null for non Posted variable');
        $this->assertEquals('test-post', $bouncer->getPostedVariable('test'), 'Should get Posted variable');

        $configs = $bouncer->getConfigs();
        $this->assertArrayHasKey('text', $configs, 'Config should have text key');
        $this->assertArrayHasKey('color', $configs, 'Config should have color key');

        $this->configs['cache_system'] = Constants::CACHE_SYSTEM_REDIS;
        $bouncer = new StandaloneBouncer($this->configs);

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\Redis', \get_class($remediation->getCacheStorage
        ()), 'Remediation cache Init');

        $this->configs['cache_system'] = Constants::CACHE_SYSTEM_MEMCACHED;
        $bouncer = new StandaloneBouncer($this->configs);

        $remediation = $bouncer->getRemediationEngine();
        $this->assertEquals('CrowdSec\RemediationEngine\CacheStorage\Memcached', \get_class
        ($remediation->getCacheStorage
        ()), 'Remediation cache Init');
    }

    /**
     * @group integration
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testCanVerifyIpInLiveModeWithCacheSystem($cacheAdapterName, $origCacheName): void
    {
        // Init context
        $this->watcherClient->setInitialState();

        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache',
            'stream_mode' => false
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);

        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        $this->cacheAdapterCheck($cacheAdapter, $origCacheName);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'Get decisions for a bad IP (for the first time, it should be a cache miss)'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'Call the same thing for the second time (now it should be a cache hit)'
        );

        $cleanRemediation1stCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP);
        $this->assertEquals(
            'bypass',
            $cleanRemediation1stCall,
            'Get decisions for a clean IP for the first time (it should be a cache miss)'
        );

        // Call the same thing for the second time (now it should be a cache hit)
        $cleanRemediation2ndCall = $bouncer->getRemediationForIp(TestHelpers::CLEAN_IP);
        $this->assertEquals('bypass', $cleanRemediation2ndCall);

        // Prune cache
        if ('PhpFilesAdapter' === $origCacheName) {
            $this->assertTrue($bouncer->pruneCache(), 'The cache should be prunable');
        }

        // Clear cache
        $this->assertTrue($bouncer->clearCache(), 'The cache should be clearable');

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('ban', $remediation3rdCall);

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        // Reset the max remediation level to its origin state
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);

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
            $cappedRemediation,
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
            $cappedRemediation,
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
            $cappedRemediation,
            'The remediation for a banned IPv6 should be ban'
        );
    }

    /**
     * @group integration
     * @dataProvider cacheAdapterConfigProvider
     */
    public function testCanVerifyIpInStreamModeWithCacheSystem($cacheAdapterName, $origCacheName): void
    {
        // Init context
        $this->watcherClient->setInitialState();
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache'
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);
        // Test cache adapter
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();
        $this->cacheAdapterCheck($cacheAdapter, $origCacheName);
        // As we are in stream mode, no live call should be done to the API.
        // Warm BlockList cache up

        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        $bouncerConfigs['bouncing_level'] = Constants::BOUNCING_LEVEL_NORMAL;
        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);
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

        // Add and remove decision
        $this->watcherClient->setSecondState();

        // Pull updates
        $bouncer->refreshBlocklistCache();


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
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' => getenv('MEMCACHED_DSN'),
            'fs_cache_path' => $this->root->url() . '/.cache'
        ];
        if ($this->useTls) {
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;
        }

        $bouncer = new StandaloneBouncer($bouncerConfigs, $this->logger);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP),
            'The cache warm up should be stored across each instantiation'
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

    /**
     * @group ban
     *
     * @return void
     * @throws BouncerException
     * @throws \CrowdSec\RemediationEngine\CacheStorage\CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testBanFlow()
    {
        $this->watcherClient->setSimpleDecision('ban');
        // Init bouncer
        $bouncerConfigs = [
            'auth_type' => $this->useTls ? Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => false,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncerNoResponse($bouncerConfigs, $this->logger);
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
    }

    /**
     * @group captcha
     *
     * @return void
     * @throws BouncerException
     * @throws \CrowdSec\RemediationEngine\CacheStorage\CacheStorageException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
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
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => $this->root->url() . '/.cache',
            'forced_test_ip' => TestHelpers::BAD_IP
        ];
        if ($this->useTls) {
            $this->addTlsConfig($bouncerConfigs, $this->useTls);
        }

        $bouncer = new StandaloneBouncerNoResponse($bouncerConfigs, $this->logger);
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
        $_SERVER['HTTP_REFERER'] = 'UNIT-TEST';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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
        // Test 1:  remote ip is as expected
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // We have set 'trust_ip_forward_array' => ['5.6.7.8'] in $configs
        $bouncer = new StandaloneBouncer($this->configs, $this->logger);
        $this->assertEquals('127.0.0.1', $bouncer->getRemoteIp(), 'Get remote IP');
        // Test 2: not bouncing exclude URI
        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
        $_SERVER['REQUEST_URI'] = self::EXCLUDED_URI;
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce excluded uri');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Will not bounce as URI is excluded/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 3: bouncing URI
        $_SERVER['REMOTE_ADDR'] = '127.0.0.3';
        $_SERVER['REQUEST_URI'] = '/home';
        $this->assertEquals(true, $bouncer->run(), 'Should bounce uri');
        // Test 4:  not bouncing URI if disabled
        $_SERVER['REMOTE_ADDR'] = '127.0.0.4';
        $bouncer = new StandaloneBouncer(array_merge($this->configs, ['bouncing_level' =>
            Constants::BOUNCING_LEVEL_DISABLED]), $this->logger);
        $this->assertEquals(false, $bouncer->run(), 'Should not bounce if disabled');

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Will not bounce as bouncing is disabled/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );

        // Test 5: throw error if config says so
        $_SERVER['REMOTE_ADDR'] = '127.0.0.5';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs,
                [
                    'display_errors' => true,
                    'api_url' => 'bad-url'
                ]
            ), $this->logger
        );

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        $errorExpected = $this->useCurl ? '/Could not resolve host/' : '/ailed to open stream/';
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
        // Test 6: NOT throw error if config says so
        $_SERVER['REMOTE_ADDR'] = '127.0.0.6';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs,
                [
                    'display_errors' => false,
                    'api_url' => 'bad-url'
                ]
            )
            , $this->logger);

        $error = '';

        try {
            $bouncer->run();
        } catch (BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertEquals('', $error, 'Should not throw error');
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*EXCEPTION_WHILE_BOUNCING/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 7 : no-forward
        $_SERVER['REMOTE_ADDR'] = '127.0.0.7';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs,
                [
                    'forced_test_forwarded_ip' => Constants::X_FORWARDED_DISABLED,
                ]
            ),
            $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is disabled/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        // Test 8 : forced X-Forwarded-for usage
        $_SERVER['REMOTE_ADDR'] = '127.0.0.8';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs,
                [
                    'forced_test_forwarded_ip' => '1.2.3.5',
                ]
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*X-Forwarded-for usage is forced.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
        // Test 9 non-authorized
        $_SERVER['REMOTE_ADDR'] = '127.0.0.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*Detected IP is not allowed for X-Forwarded-for usage.*"x_forwarded_for_ip":"1.2.3.5"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 10 authorized
        $_SERVER['REMOTE_ADDR'] = '5.6.7.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.10';
        $bouncer = new StandaloneBouncer(
            array_merge(
                $this->configs
            ), $this->logger
        );
        $bouncer->run();
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*100.*Detected IP is allowed for X-Forwarded-for usage.*"original_ip":"5.6.7.8","x_forwarded_for_ip":"127.0.0.10"/',
            file_get_contents($this->root->url() . '/' . $this->debugFile),
            'Debug log content should be correct'
        );
    }
}
