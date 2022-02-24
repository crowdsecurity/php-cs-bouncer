<?php

declare(strict_types=1);
require __DIR__ . '/TestHelpers.php';
require __DIR__ . '/WatcherClient.php';

use CrowdSecBouncer\ApiCache;
use CrowdSecBouncer\ApiClient;
use CrowdSecBouncer\Bouncer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

final class GeolocationTest extends TestCase
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

    public function maxmindConfigProvider(): array
    {
        return TestHelpers::maxmindConfigProvider();
    }

    /**
     * @group integration
     * @covers       \Bouncer
     * @dataProvider maxmindConfigProvider
     * @group ignore_
     * @throws \Symfony\Component\Cache\Exception\CacheException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInLiveMode(array $maxmindConfig):
    void
    {
        // Check if MaxMind database exist
        if (!file_exists($maxmindConfig['database_path'])) {
            $this->fail('There must be a MaxMind Database here: '.$maxmindConfig['database_path']);
        }
        // Init context
        $cacheAdapter = new PhpFilesAdapter('php_array_adapter_backup_cache', 0,
            TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR);
        $this->watcherClient->setInitialState();
        $cacheAdapter->clear();

        $geolocationConfig = [
            'save_in_session' => false,
            'enabled' => true,
            'type' => 'maxmind',
            'maxmind' => [
                'database_type' => $maxmindConfig['database_type'],
                'database_path' => $maxmindConfig['database_path']
            ]
        ];
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
            'geolocation' => $geolocationConfig
        ];
        $bouncer = new Bouncer(null, $this->logger, $apiCache);
        $bouncer->configure($bouncerConfig);

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Get decisions for a clean IP but bad country (captcha)'
        );

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::IP_FRANCE),
            'Get decisions for a clean IP and clean country'
        );

        // Disable Geolocation feature
        $geolocationConfig['enabled'] = false;
        $bouncerConfig['geolocation'] = $geolocationConfig;
        $bouncer = new Bouncer(null, $this->logger, $apiCache);
        $bouncer->configure($bouncerConfig);
        $cacheAdapter->clear();

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Get decisions for a clean IP and bad country but with geolocation disabled'
        );


        // Enable again geolocation and change testing conditions
        $this->watcherClient->setSecondState();
        $geolocationConfig['enabled'] = true;
        $bouncerConfig['geolocation'] = $geolocationConfig;
        $bouncer = new Bouncer(null, $this->logger, $apiCache);
        $bouncer->configure($bouncerConfig);
        $cacheAdapter->clear();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Get decisions for a bad IP (ban) and bad country (captcha)'
        );


        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_FRANCE),
            'Get decisions for a bad IP (ban) and clean country'
        );

    }


    /**
     * @group integration
     * @covers       \Bouncer
     * @dataProvider maxmindConfigProvider
     * @group ignore_
     * @throws \Symfony\Component\Cache\Exception\CacheException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInStreamMode(array $maxmindConfig):
    void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');

    }
}
