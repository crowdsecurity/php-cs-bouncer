<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\Constants;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationForIp
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::__construct
 * @uses \CrowdSecBouncer\AbstractBouncer::capRemediationLevel
 * @uses \CrowdSecBouncer\AbstractBouncer::configure
 * @uses \CrowdSecBouncer\AbstractBouncer::getConfig
 * @uses \CrowdSecBouncer\AbstractBouncer::getConfigs
 * @uses \CrowdSecBouncer\AbstractBouncer::getLogger
 * @uses \CrowdSecBouncer\AbstractBouncer::getRemediationEngine
 * @uses \CrowdSecBouncer\AbstractBouncer::handleCache
 * @uses \CrowdSecBouncer\AbstractBouncer::handleClient
 * @uses \CrowdSecBouncer\AbstractBouncer::refreshBlocklistCache
 * @uses \CrowdSecBouncer\Configuration::addBouncerNodes
 * @uses \CrowdSecBouncer\Configuration::addCacheNodes
 * @uses \CrowdSecBouncer\Configuration::addConnectionNodes
 * @uses \CrowdSecBouncer\Configuration::addDebugNodes
 * @uses \CrowdSecBouncer\Configuration::addTemplateNodes
 * @uses \CrowdSecBouncer\Configuration::cleanConfigs
 * @uses \CrowdSecBouncer\Configuration::getConfigTreeBuilder
 * @uses \CrowdSecBouncer\AbstractBouncer::clearCache
 */
final class GeolocationTest extends TestCase
{
    /** @var WatcherClient */
    private $watcherClient;

    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $useCurl;
    /** @var bool */
    private $useTls;
    /**
     * @var array
     */
    private $configs;

    private function addTlsConfig(&$bouncerConfigs, $tlsPath)
    {
        $bouncerConfigs['tls_cert_path'] = $tlsPath . '/bouncer.pem';
        $bouncerConfigs['tls_key_path'] = $tlsPath . '/bouncer-key.pem';
        $bouncerConfigs['tls_ca_cert_path'] = $tlsPath . '/ca-chain.pem';
        $bouncerConfigs['tls_verify_peer'] = true;
    }

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

    public function maxmindConfigProvider(): array
    {
        return TestHelpers::maxmindConfigProvider();
    }

    private function handleMaxMindConfig(array $maxmindConfig): array
    {
        // Check if MaxMind database exist
        if (!file_exists($maxmindConfig['database_path'])) {
            $this->fail('There must be a MaxMind Database here: ' . $maxmindConfig['database_path']);
        }

        return [
            'cache_duration' => 0,
            'enabled' => true,
            'type' => 'maxmind',
            'maxmind' => [
                'database_type' => $maxmindConfig['database_type'],
                'database_path' => $maxmindConfig['database_path'],
            ],
        ];
    }

    /**
     * @dataProvider maxmindConfigProvider
     *
     * @throws \Symfony\Component\Cache\Exception\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInLiveMode(array $maxmindConfig): void
    {
        // Init context
        $this->watcherClient->setInitialState();

        // Init bouncer
        $geolocationConfig = $this->handleMaxMindConfig($maxmindConfig);
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'geolocation' => $geolocationConfig,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR,
            'stream_mode' => false,
        ];

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);

        $bouncer->clearCache();

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
        $bouncerConfigs['geolocation'] = $geolocationConfig;
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $bouncer->clearCache();

        $this->assertEquals(
            'bypass',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Get decisions for a clean IP and bad country but with geolocation disabled'
        );

        // Enable again geolocation and change testing conditions
        $this->watcherClient->setSecondState();
        $geolocationConfig['enabled'] = true;
        $bouncerConfigs['geolocation'] = $geolocationConfig;
        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $bouncer->clearCache();

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
     *
     * @dataProvider maxmindConfigProvider
     *
     * @throws \Symfony\Component\Cache\Exception\CacheException|\Psr\Cache\InvalidArgumentException
     */
    public function testCanVerifyIpAndCountryWithMaxmindInStreamMode(array $maxmindConfig): void
    {
        // Init context
        $this->watcherClient->setInitialState();
        // Init bouncer
        $geolocationConfig = $this->handleMaxMindConfig($maxmindConfig);
        $bouncerConfigs = [
            'api_key' => TestHelpers::getBouncerKey(),
            'api_url' => TestHelpers::getLapiUrl(),
            'stream_mode' => true,
            'geolocation' => $geolocationConfig,
            'use_curl' => $this->useCurl,
            'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
            'fs_cache_path' => TestHelpers::PHP_FILES_CACHE_ADAPTER_DIR,
        ];

        $client = new BouncerClient($bouncerConfigs);
        $cache = new PhpFiles($bouncerConfigs);
        $lapiRemediation = new LapiRemediation($bouncerConfigs, $client, $cache);

        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$bouncerConfigs, $lapiRemediation]);
        $cacheAdapter = $bouncer->getRemediationEngine()->getCacheStorage();
        $cacheAdapter->clear();

        // Warm BlockList cache up
        $bouncer->refreshBlocklistCache();

        $this->logger->debug('', ['message' => 'Refresh the cache just after the warm up. Nothing should append.']);
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Should captcha a clean IP coming from a bad country (captcha)'
        );

        // Add and remove decision
        $this->watcherClient->setSecondState();

        $this->assertEquals(
            'captcha',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'Should still captcha a bad IP (ban) coming from a bad country (captcha) as cache has not been refreshed'
        );

        // Pull updates
        $bouncer->refreshBlocklistCache();

        $this->assertEquals(
            'ban',
            $bouncer->getRemediationForIp(TestHelpers::IP_JAPAN),
            'The new decision should now be added, so the previously captcha IP should now be ban'
        );
    }
}
