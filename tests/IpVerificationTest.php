<?php

declare(strict_types=1);
require(dirname(__FILE__) . "/TestHelpers.php");
require(dirname(__FILE__) . "/WatcherClient.php");

use CrowdSecBouncer\ApiCache;
use CrowdSecBouncer\ApiClient;
use CrowdSecBouncer\Bouncer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

define("HOST_IS_UP", true);
define("HOST_IS_DOWN", false);

/*
TODO P2 Instanciate all the configuration
TODO P2 testThrowErrorWhenMissAndApiIsNotReachable()
TODO P2 testThrowErrorWhenMissAndApiTimeout()
TODO P2 testCanVerifyCaptchableIp()
TODO P2 testCanHandleCacheSaturation()
TODO P2 testCanNotUseCapiInLiveMode()
TODO P2 testCanVerifyIpInStreamModeWithCacheSystemBeforeWarmingTheCacheUp() https://stackoverflow.com/questions/5683592/phpunit-assert-that-an-exception-was-thrown
*/

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
     * @covers Bouncer
     */
    /*
    TODO P2
    public function testCanVerifyIpInLiveModeWithoutCacheSystem(): void
    {
        // Init bouncer
        $basicLapiContext = TestHelpers::setupBasicLapiInLiveModeContext();
        $badIp = $basicLapiContext['bad_ip'];
        $config = $basicLapiContext['config'];
        $bouncer = new Bouncer();
        $bouncer->configure($config);

        // Get decisions for a bad IP
        $remediation = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals($remediation, 'ban');
    }*/
    /**
     * @group integration
     * @covers Bouncer
     * @dataProvider cacheAdapterProvider
     * @group ignore_
     */
    public function testCanVerifyIpInLiveModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock, $this->logger);
        $basicLapiContext = TestHelpers::setupBasicLapiInLiveModeContext();
        $badIp = $basicLapiContext['bad_ip'];
        $cleanIp = $basicLapiContext['clean_ip'];
        $config = $basicLapiContext['config'];
        $bouncer = new Bouncer($apiCache, $this->logger);
        $bouncer->configure($config, $cacheAdapter);

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

        // Clear cache
        $cacheAdapter->clear();

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('ban', $remediation3rdCall);

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $config['max_remediation_level'] = 'captcha';
        $bouncer->configure($config, $cacheAdapter);
        $cappedRemediation = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        $config['max_remediation_level'] = 'ban';
        $bouncer->configure($config, $cacheAdapter);
    }

    /**
     * @group integration
     * @covers Bouncer
     * @dataProvider cacheAdapterProvider
     * @group ignore_
     * TODO P2 check exception when calling but cache was not warmed up
     */
    public function testCanVerifyIpInStreamModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->setConstructorArgs([$this->logger])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock, $this->logger);
        $basicLapiContext = TestHelpers::setupBasicLapiInLiveModeContext();
        $badIp = $basicLapiContext['bad_ip'];
        $cleanIp = $basicLapiContext['clean_ip'];
        $newlyBadIp = $basicLapiContext['newly_bad_ip'];
        $badIp = $basicLapiContext['bad_ip'];
        $config = $basicLapiContext['config'];
        $config['live_mode'] = false;
        $bouncer = new Bouncer($apiCache, $this->logger);
        $bouncer->configure($config, $cacheAdapter);

        // As we are in stream mode, no live call should be done to the API.
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(0))->method('getFilteredDecisions');

        // Warm BlockList cache up
        $bouncer->warmBlocklistCacheUp();

        $this->logger->debug('Refresh the cache just after the warm up. Nothing should append.');
        // TODO P3 test this assertion
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($badIp),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // Reconfigure the bouncer to set maximum remediation level to "captcha"
        $config['max_remediation_level'] = 'captcha';
        $bouncer->configure($config, $cacheAdapter);
        $cappedRemediation = $bouncer->getRemediationForIp($badIp);
        $this->assertEquals('captcha', $cappedRemediation, 'The remediation for the banned IP should now be "captcha"');
        $config['max_remediation_level'] = 'ban';
        $bouncer->configure($config, $cacheAdapter);

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

        //sleep(5);

        $this->logger->debug('Refresh 2nd time the cache. Nothing should append.');
        // TODO P3 test this assertion
        $bouncer->refreshBlocklistCache();

        //sleep(5);

        $this->logger->debug('Refresh 3rd time the cache. Nothing should append.');
        // TODO P3 test this assertion
        $bouncer->refreshBlocklistCache();

        //sleep(5);

        //$this->logger->debug('Refresh 4th time the cache. Nothing should append.');
        //$bouncer->refreshBlocklistCache();

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
    }

    /**
     * @group integration
     * @covers Bouncer
     * @dataProvider cacheAdapterProvider
     */
    /*
    TODO P3
     public function testCanNotVerifyIpViaCapiInLiveMode(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }*/
}
