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
    public function testCanVerifyIpInRuptureModeWithCacheSystem(AbstractAdapter $cacheAdapter): void
    {
        $cacheAdapter->clear();
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock);
        $basicLapiContext = TestHelpers::setupBasicLapiInRuptureModeContext();
        $badIp = $basicLapiContext['bad_ip'];
        $cleanIp = $basicLapiContext['clean_ip'];
        $config = $basicLapiContext['config'];
        $bouncer = new Bouncer($apiCache);
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
            'clean',
            $cleanRemediation1stCall,
            'Get decisions for a clean IP for the first time (it should be a cache miss)'
        );

        // Call the same thing for the second time (now it should be a cache hit)
        $cleanRemediation2ndCall = $bouncer->getRemediationForIp($cleanIp);
        $this->assertEquals('clean', $cleanRemediation2ndCall);

        // Clear cache
        $cacheAdapter->clear();

        // Call one more time (should miss as the cache has been cleared)

        $remediation3rdCall = $bouncer->getRemediationForIp($badIp);
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
        $cacheAdapter->clear();
        // Init bouncer
        /** @var ApiClient */
        $apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $apiCache = new ApiCache($apiClientMock);
        $basicLapiContext = TestHelpers::setupBasicLapiInRuptureModeContext();
        $badIp = $basicLapiContext['bad_ip'];
        $cleanIp = $basicLapiContext['clean_ip'];
        $config = $basicLapiContext['config'];
        $config['rupture_mode'] = false;
        $bouncer = new Bouncer($apiCache);
        $bouncer->configure($config, $cacheAdapter);

        // A the end of test, we shoud have exactly 0 "cache miss")
        /** @var MockObject $apiClientMock */
        $apiClientMock->expects($this->exactly(0))->method('getFilteredDecisions');

        // Warm BlockList cache up
        $bouncer->warmBlocklistCacheUp();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp($badIp),
            'Get decisions for a bad IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        $this->assertEquals(
            'clean',
            $bouncer->getRemediationForIp($cleanIp),
            'Get decisions for a clean IP for the first time (as the cache has been warmed up should be a cache hit)'
        );

        // TODO P1 Add and remove decision and try updating cache with refreshBlocklistCache()

        // Clear cache
        //$cacheAdapter->clear();

        // Call the same thing for the second time (now it should be a cache miss)
        //$remediation2ndCall = $bouncer->getRemediationForIp($badIp);
        //$this->assertEquals('ban', $remediation2ndCall);
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
