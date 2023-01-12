<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\StandaloneBouncer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IpVerificationTest extends TestCase
{
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

    protected function setUp(): void
    {
        $this->useTls = (string) getenv('BOUNCER_TLS_PATH');
        $this->useCurl = (bool) getenv('USE_CURL');
        $this->logger = TestHelpers::createLogger();

        $bouncerConfigs = [
            'auth_type' => $this->useTls ? \CrowdSec\LapiClient\Constants::AUTH_TLS : Constants::AUTH_KEY,
            'api_key' => getenv('BOUNCER_KEY'),
            'api_url' => getenv('LAPI_URL'),
            'use_curl' => $this->useCurl,
            'user_agent_suffix' => 'testphpbouncer',
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
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR,
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
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR
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

        $this->logger->debug('', ['message' => 'Refresh 2nd time the cache. Nothing should append.']);
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
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR
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
    }
}
