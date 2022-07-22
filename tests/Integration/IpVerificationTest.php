<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\ApiCache;
use CrowdSecBouncer\ApiClient;
use CrowdSecBouncer\Bouncer;
use CrowdSecBouncer\Constants;
use PHPUnit\Framework\MockObject\MockObject;
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

    protected function setUp(): void
    {
        $this->logger = TestHelpers::createLogger();
        $this->useCurl = (bool) getenv('USE_CURL');
        $this->watcherClient = new WatcherClient($this->logger, ['use_curl' => $this->useCurl]);
    }

    public function cacheAdapterProvider(): array
    {
        return TestHelpers::cacheAdapterProvider();
    }

    /**
     * @group integration
     * @dataProvider cacheAdapterProvider
     */
    public function testCanVerifyIpInLiveModeWithCacheSystem($cacheAdapter, $origCacheName): void
    {
        // Init context

        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();

        // Init bouncer
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'use_curl' => $this->useCurl,
            'api_user_agent' => 'Unit test/'.Constants::BASE_USER_AGENT,
        ];

        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger, $bouncerConfigs])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($this->logger, $apiClientMock, $cacheAdapter, null, $bouncerConfigs);

        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);

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

        // At the end of test, we should have exactly 3 "cache miss")
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(4))->method('getFilteredDecisions');

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
        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        // Reset the max remediation level to its origin state
        unset($bouncerConfigs['max_remediation_level']);
        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);

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
     * @dataProvider cacheAdapterProvider
     */
    public function testCanVerifyIpInStreamModeWithCacheSystem($cacheAdapter, $origCacheName): void
    {
        // Init context

        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();

        // Init bouncer
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'api_user_agent' => 'Unit test/'.Constants::BASE_USER_AGENT,
            'stream_mode' => true,
            'use_curl' => $this->useCurl
        ];

        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger, $bouncerConfigs])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $apiCache = new ApiCache($this->logger, $apiClientMock, $cacheAdapter, null, $bouncerConfigs);

        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);

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

        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(0))->method('getFilteredDecisions');

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
        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);
        $cappedRemediation = $bouncer->getRemediationForIp(TestHelpers::BAD_IP);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        unset($bouncerConfigs['max_remediation_level']);
        $bouncer = new Bouncer(null, $this->logger, $apiCache, $bouncerConfigs);
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

        $this->logger->debug('', ['message' => 'Refresh 3rd time the cache. Nothing should append.']);
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

        // Setup an new instance.
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'use_curl' => $this->useCurl,
            'api_user_agent' => 'Unit test/'.Constants::BASE_USER_AGENT,
        ];

        /** @var ApiClient */
        $apiClientMock2 = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger,$bouncerConfigs])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache2 = new ApiCache($this->logger, $apiClientMock2, $cacheAdapter, null, $bouncerConfigs);


        $bouncer = new Bouncer(null, $this->logger, $apiCache2, $bouncerConfigs);


        // The cache should still be warmed up, even for a new instance

        /** @var MockObject $apiClientMock2 */
        $apiClientMock2->expects($this->exactly(0))->method('getFilteredDecisions');

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
