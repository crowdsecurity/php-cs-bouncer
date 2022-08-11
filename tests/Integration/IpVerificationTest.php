<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\Constants;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IpVerificationTest extends TestCase
{
    /** @var WatcherClient */
    private $watcherClient;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool  */
    private $useCurl;

    /** @var bool  */
    private $useTls;

    protected function setUp(): void
    {
        $this->logger = TestHelpers::createLogger();
        $this->useCurl = (bool) getenv('USE_CURL');
        $this->useTls = (string) getenv('BOUNCER_TLS_PATH');
        $this->watcherClient = new WatcherClient(['use_curl' => $this->useCurl], $this->logger);
    }

    public function cacheAdapterConfigProvider(): array
    {
        return TestHelpers::cacheAdapterConfigProvider();
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
            'api_user_agent' => TestHelpers::UNIT_TEST_AGENT_PREFIX . '/' . Constants::BASE_USER_AGENT,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' =>  getenv('MEMCACHED_DSN'),
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR
        ];
        if($this->useTls){
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;

        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        // Test cache adapter
        $cacheAdapter = $bouncer->getCacheAdapter();
        $cacheAdapter->clear();

        switch ($origCacheName) {
            case 'PhpFilesAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\TagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'MemcachedAdapter':
                $this->assertEquals(
                    'CrowdSecBouncer\Fixes\Memcached\TagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'RedisAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\RedisTagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            default:
                break;
        }

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
        $bouncerConfigs['max_remediation_level'] = 'captcha';
        $bouncer = new Bouncer( $bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        // Reset the max remediation level to its origin state
        unset($bouncerConfigs['max_remediation_level']);
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(new \DateTime(), '24h', WatcherClient::HOURS24, TestHelpers::BAD_IP.'/'
                                                                     .TestHelpers::LARGE_IPV4_RANGE, 'ban');
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('ban', $cappedRemediation, 'The remediation for the banned IP with a too large range should now be "ban" as we are in live mode');

        $this->logger->info('', ['message' => 'set "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(new \DateTime(), '24h', WatcherClient::HOURS24, TestHelpers::BAD_IPV6.'/'.TestHelpers::IPV6_RANGE, 'ban');
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals('ban', $cappedRemediation, 'The remediation for the banned IPV6 with a too large range should now be "ban" as we are in live mode');
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
            'api_user_agent' => TestHelpers::UNIT_TEST_AGENT_PREFIX . '/' . Constants::BASE_USER_AGENT,
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' =>  getenv('MEMCACHED_DSN'),
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR
        ];
        if($this->useTls){
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;
        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        // Test cache adapter
        $cacheAdapter = $bouncer->getCacheAdapter();
        $cacheAdapter->clear();

        switch ($origCacheName) {
            case 'PhpFilesAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\TagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'MemcachedAdapter':
                $this->assertEquals(
                    'CrowdSecBouncer\Fixes\Memcached\TagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            case 'RedisAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\RedisTagAwareAdapter',
                    get_class($cacheAdapter),
                    'Tested adapter should be correct'
                );
                break;
            default:
                break;
        }
        // As we are in stream mode, no live call should be done to the API.
        // Warm BlockList cache up

        $bouncer->refreshBlocklistCache();

        $this->logger->debug('', ['message' => 'Refresh the cache just after the warm up. Nothing should append.']);
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::BAD_IP),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfigs['max_remediation_level'] = 'captcha';
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        unset($bouncerConfigs['max_remediation_level']);
        $bouncer = new Bouncer($bouncerConfigs, $this->logger);
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
            'api_user_agent' => TestHelpers::UNIT_TEST_AGENT_PREFIX . '/' . Constants::BASE_USER_AGENT,
            'cache_system' => $cacheAdapterName,
            'redis_dsn' => getenv('REDIS_DSN'),
            'memcached_dsn' =>  getenv('MEMCACHED_DSN'),
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR
        ];
        if($this->useTls){
            $bouncerConfigs['tls_cert_path'] = $this->useTls . '/bouncer.pem';
            $bouncerConfigs['tls_key_path'] = $this->useTls . '/bouncer-key.pem';
            $bouncerConfigs['tls_ca_cert_path'] = $this->useTls . '/ca-chain.pem';
            $bouncerConfigs['tls_verify_peer'] = true;

        }

        $bouncer = new Bouncer($bouncerConfigs, $this->logger);

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::NEWLY_BAD_IP),
            'The cache warm up should be stored across each instantiation'
        );

        $this->logger->info('', ['message' => 'set "Large IPV4 range banned" + "IPV6 range banned" state']);
        $this->watcherClient->deleteAllDecisions();
        $this->watcherClient->addDecision(new \DateTime(), '24h', WatcherClient::HOURS24, TestHelpers::BAD_IP.'/'.TestHelpers::LARGE_IPV4_RANGE, 'ban');
        $this->watcherClient->addDecision(new \DateTime(), '24h', WatcherClient::HOURS24, TestHelpers::BAD_IPV6.'/'.TestHelpers::IPV6_RANGE, 'ban');
        // Pull updates
        $bouncer->refreshBlocklistCache();

        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('bypass', $cappedRemediation, 'The remediation for the banned IP with a too large range should now be "bypass" as we are in stream mode');
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IPV6);
        $this->assertEquals('bypass', $cappedRemediation, 'The remediation for the banned IPV6 with a too large range should now be "bypass" as we are in stream mode');
    }
}
