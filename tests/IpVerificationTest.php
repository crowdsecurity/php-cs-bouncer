<?php

declare(strict_types=1);
require __DIR__.'/TestHelpers.php';
require __DIR__.'/WatcherClient.php';

use CrowdSecBouncer\ApiCache;
use CrowdSecBouncer\ApiClient;
use CrowdSecBouncer\Bouncer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\PruneableInterface;

final class IpVerificationTest extends TestCase
{
    /** @var WatcherClient */
    private $watcherClient;

    /** @var LoggerInterface */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = TestHelpers::createLogger();

        $this->watcherClient = new WatcherClient($this->logger);
        $this->watcherClient->configure();
    }

    public function cacheAdapterProvider(): array
    {
        return TestHelpers::cacheAdapterProvider();
    }

    /**
     * @group integration
     * @covers \Bouncer
     * @dataProvider cacheAdapterProvider
     * @group ignore_
     */
    public function testCanVerifyIpInLiveModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        // Init context

        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();
        $badIp = TestHelpers::BAD_IP;
        $cleanIp = TestHelpers::CLEAN_IP;

        // Init bouncer

        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($this->logger, $apiClientMock, $cacheAdapter);
        $bouncerConfig = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
        ];
        $bouncer = new Bouncer(null, $this->logger, $apiCache);
        $bouncer->configure($bouncerConfig);

        // A the end of test, we shoud have exactly 3 "cache miss")
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(3))->method('getFilteredDecisions');

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($badIp),
            'Get decisions for a bad IP (for the first time, it should be a cache miss)'
        );

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($badIp),
            'Call the same thing for the second time (now it should be a cache hit)'
        );

        $cleanRemediation1stCall = $bouncer->getRemediationForIp($cleanIp);
        $this->assertEquals(
            'bypass',
            $cleanRemediation1stCall,
            'Get decisions for a clean IP for the first time (it should be a cache miss)'
        );

        // Call the same thing for the second time (now it should be a cache hit)
        $cleanRemediation2ndCall = $bouncer->getRemediationForIp($cleanIp);
        $this->assertEquals('bypass', $cleanRemediation2ndCall);

        // Prune cache
        if ($cacheAdapter instanceof PruneableInterface) {
            $this->assertTrue($bouncer->pruneCache(), 'The cache should be prunable');
        }

        // Clear cache
        $this->assertTrue($bouncer->clearCache(), 'The cache should be clearable');

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('ban', $remediation3rdCall);

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfig['max_remediation_level'] = 'captcha';
        $bouncer->configure($bouncerConfig, $cacheAdapter);
        $cappedRemediation = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        unset($bouncerConfig['max_remediation_level']);
        $bouncer->configure($bouncerConfig, $cacheAdapter);
    }

    /**
     * @group integration
     * @covers \Bouncer
     * @dataProvider cacheAdapterProvider
     * @group ignore_
     */
    public function testCanVerifyIpInStreamModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        // Init context

        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();
        $badIp = TestHelpers::BAD_IP;
        $cleanIp = TestHelpers::CLEAN_IP;
        $newlyBadIp = TestHelpers::NEWLY_BAD_IP;

        // Init bouncer

        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($this->logger, $apiClientMock);

        $bouncerConfig = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'live_mode' => false,
        ];
        $bouncer = new Bouncer($cacheAdapter, $this->logger, $apiCache);
        $bouncer->configure($bouncerConfig);

        // As we are in stream mode, no live call should be done to the API.

        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(0))->method('getFilteredDecisions');

        // Warm BlockList cache up

        $bouncer->refreshBlocklistCache();

        $this->logger->debug('Refresh the cache just after the warm up. Nothing should append.');
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($badIp),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $bouncerConfig['max_remediation_level'] = 'captcha';
        $bouncer->configure($bouncerConfig, $cacheAdapter);
        $cappedRemediation = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        unset($bouncerConfig['max_remediation_level']);
        $bouncer->configure($bouncerConfig, $cacheAdapter);

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp($cleanIp),
            'Get decisions for a clean IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Preload the remediation to prepare the next tests.
        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp($newlyBadIp),
            'Preload the bypass remediation to prepare the next tests'
        );

        // Add and remove decision
        $this->watcherClient->setSecondState();

        // Pull updates
        $bouncer->refreshBlocklistCache();

        $this->logger->debug('Refresh 2nd time the cache. Nothing should append.');
        $bouncer->refreshBlocklistCache();

        $this->logger->debug('Refresh 3rd time the cache. Nothing should append.');
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($newlyBadIp),
            'The new decision should now be added, so the previously clean IP should now be bad'
        );

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp($badIp),
            'The old decisions should now be removed, so the previously bad IP should now be clean'
        );

        // Setup an new instance.

        /** @var ApiClient */
        $apiClientMock2 = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache2 = new ApiCache($this->logger, $apiClientMock2);

        $bouncerConfig = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'live_mode' => false,
        ];
        $bouncer = new Bouncer($cacheAdapter, $this->logger, $apiCache2);
        $bouncer->configure($bouncerConfig);

        // The cache should still be warmed up, even for a new instance

        /** @var MockObject $apiClientMock2 */
        $apiClientMock2->expects($this->exactly(0))->method('getFilteredDecisions');

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($newlyBadIp),
            'The cache warm up should be stored across each instanciation'
        );
    }
}
