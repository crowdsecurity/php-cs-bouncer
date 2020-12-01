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

define("HOST_IS_UP", true);
define("HOST_IS_DOWN", false);

/*
TODO P2 Instanciate all the configuration
TODO P2 testThrowErrorWhenMissAndApiIsNotReachable()
TODO P2 testThrowErrorWhenMissAndApiTimeout()
TODO P2 testCanVerifyCaptchableIp()
TODO P1 testCanVerifyCleanIp()
TODO P1 testCanCacheTheCleanIp()
TODO P2 testCanHandleCacheSaturation()
TODO P2 testCanNotUseCapiInRuptureMode()
TODO P2 testCanVerifyIpInStreamModeWithCacheSystemBeforeWarmingTheCacheUp() https://stackoverflow.com/questions/5683592/phpunit-assert-that-an-exception-was-thrown
*/

final class IpVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        WatcherClient::setCrowdSecContext();
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
    public function testCanVerifyIpInRuptureModeWithoutCacheSystem(): void
    {
        // Init bouncer
        $basicLapiContext = TestHelpers::setupBasicLapiInRuptureModeContext();
        $blockedIp = $basicLapiContext['blocked_ip'];
        $config = $basicLapiContext['config'];
        $bouncer = new Bouncer();
        $bouncer->configure($config);

        // Get decisions for a blocked IP
        $remediation = $bouncer->getRemediationForIp($blockedIp);
        $this->assertEquals($remediation, 'ban');
    }*/

    /**
     * @group integration
     * @covers Bouncer
     * @dataProvider cacheAdapterProvider
     * @group ignore_
     */
    public function testCanVerifyIpInRuptureModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock);
        $basicLapiContext = TestHelpers::setupBasicLapiInRuptureModeContext();
        $blockedIp = $basicLapiContext['blocked_ip'];
        $config = $basicLapiContext['config'];
        $bouncer = new Bouncer($apiCache);
        $bouncer->configure($config, $cacheAdapter);

        // A the end of test, we shoud have exactly 2 "cache miss")
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(2))->method('getFilteredDecisions');

        // Get decisions for a Blocked IP (for the first time, it should be a cache miss)
        $remediation1stCall = $bouncer->getRemediationForIp($blockedIp);
        $this->assertEquals('ban', $remediation1stCall);

        // Call the same thing for the second time (now it should be a cache miss)
        $remediation2ndCall = $bouncer->getRemediationForIp($blockedIp);
        $this->assertEquals('ban', $remediation2ndCall);

        // Clear cache
        $cacheAdapter->clear();

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp($blockedIp);
        $this->assertEquals('ban', $remediation3rdCall);
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
        
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock);
        $basicLapiContext = TestHelpers::setupBasicLapiInRuptureModeContext();
        $blockedIp = $basicLapiContext['blocked_ip'];
        $config = $basicLapiContext['config'];
        $config['rupture_mode'] = false;
        $bouncer = new Bouncer($apiCache);
        $bouncer->configure($config, $cacheAdapter);

        // A the end of test, we shoud have exactly 2 "cache miss")
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(0))->method('getFilteredDecisions');

        // Warm BlockList cache up
        $bouncer->warmBlocklistCacheUp();

        // Get decisions for a Blocked IP (for the first time, but as the cache has been warmed up should be a cache hit!)
        $remediation1stCall = $bouncer->getRemediationForIp($blockedIp);
        $this->assertEquals('ban', $remediation1stCall);

        // TODO P1 Add and remove decision and try updating cache

        // Clear cache
        //$cacheAdapter->clear();

        // Call the same thing for the second time (now it should be a cache miss)
        //$remediation2ndCall = $bouncer->getRemediationForIp($blockedIp);
        //$this->assertEquals('ban', $remediation2ndCall);

        // TODO ADD new decisions to LAPI and test refreshBlocklistCache()
    }

    /**
     * @group integration
     * @covers Bouncer
     * @dataProvider cacheAdapterProvider
     */
    /*
    TODO P3
     public function testCanNotVerifyIpViaCapiInRuptureMode(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }*/
}
