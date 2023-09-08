<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Unit;

/**
 * Test for abstract bouncer.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\Common\Logger\FileLog;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::configure
 * @covers \CrowdSecBouncer\AbstractBouncer::getConfig
 * @covers \CrowdSecBouncer\AbstractBouncer::getLogger
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationEngine
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCache
 * @covers \CrowdSecBouncer\Configuration::addBouncerNodes
 * @covers \CrowdSecBouncer\Configuration::addCacheNodes
 * @covers \CrowdSecBouncer\Configuration::addConnectionNodes
 * @covers \CrowdSecBouncer\Configuration::addDebugNodes
 * @covers \CrowdSecBouncer\Configuration::addTemplateNodes
 * @covers \CrowdSecBouncer\Configuration::cleanConfigs
 * @covers \CrowdSecBouncer\Configuration::getConfigTreeBuilder
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldNotCheckResolution
 * @covers \CrowdSecBouncer\AbstractBouncer::bounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::capRemediationLevel
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationForIp
 * @covers \CrowdSecBouncer\AbstractBouncer::getTrustForwardedIpBoundsList
 * @covers \CrowdSecBouncer\AbstractBouncer::handleForwardedFor
 *
 * @uses \CrowdSecBouncer\AbstractBouncer::__construct
 * @uses \CrowdSecBouncer\AbstractBouncer::buildClient
 * @uses \CrowdSecBouncer\AbstractBouncer::buildRemediationEngine
 * @uses \CrowdSecBouncer\AbstractBouncer::handleRemediation
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldTrustXforwardedFor
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldBounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::checkCaptcha
 * @covers \CrowdSecBouncer\AbstractBouncer::buildCaptchaCouple
 * @covers \CrowdSecBouncer\Fixes\Gregwar\Captcha\CaptchaBuilder::writePhrase
 * @covers \CrowdSecBouncer\AbstractBouncer::getCache
 * @covers \CrowdSecBouncer\AbstractBouncer::getBanHtml
 * @covers \CrowdSecBouncer\Template::__construct
 * @covers \CrowdSecBouncer\Template::render
 * @covers \CrowdSecBouncer\AbstractBouncer::getCaptchaHtml
 * @covers \CrowdSecBouncer\AbstractBouncer::clearCache
 * @covers \CrowdSecBouncer\AbstractBouncer::pruneCache
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshBlocklistCache
 * @covers \CrowdSecBouncer\AbstractBouncer::testCacheConnection
 */
final class AbstractCapiBouncerTest extends TestCase
{
    private const EXCLUDED_URI = '/favicon.ico';
    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var FileLog
     */
    private $logger;
    /**
     * @var string
     */
    private $prodFile;
    /**
     * @var vfsStreamDirectory
     */
    private $root;
    /**
     * @var FileStorage
     */
    private $storage;

    protected $configs = [
        // ============================================================================#
        // Bouncer configs
        // ============================================================================#
        'use_curl' => false,
        'debug_mode' => true,
        'disable_prod_log' => false,
        'log_directory_path' => __DIR__ . '/.logs',
        'display_errors' => true,
        'forced_test_ip' => '',
        'forced_test_forwarded_ip' => '',
        'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,
        'trust_ip_forward_array' => [['005.006.007.008', '005.006.007.008']],
        'excluded_uris' => [self::EXCLUDED_URI],
        'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
        'captcha_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CAPTCHA,
        'custom_css' => '',
        'hide_mentions' => false,
        'color' => [
            'text' => [
                'primary' => 'black',
                'secondary' => '#AAA',
                'button' => 'white',
                'error_message' => '#b90000',
            ],
            'background' => [
                'page' => '#eee',
                'container' => 'white',
                'button' => '#626365',
                'button_hover' => '#333',
            ],
        ],
        'text' => [
            'captcha_wall' => [
                'tab_title' => 'Oops..',
                'title' => 'Hmm, sorry but...',
                'subtitle' => 'Please complete the security check.',
                'refresh_image_link' => 'refresh image',
                'captcha_placeholder' => 'Type here...',
                'send_button' => 'CONTINUE',
                'error_message' => 'Please try again.',
                'footer' => '',
            ],
            'ban_wall' => [
                'tab_title' => 'Oops..',
                'title' => '🤭 Oh!',
                'subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
                'footer' => '',
            ],
        ],
        // ============================================================================#
        // Client configs
        // ============================================================================#
        'auth_type' => Constants::AUTH_KEY,
        'tls_cert_path' => '',
        'tls_key_path' => '',
        'tls_verify_peer' => true,
        'tls_ca_cert_path' => '',
        'api_key' => 'unit-test',
        'api_url' => Constants::DEFAULT_LAPI_URL,
        'api_timeout' => 1,
        // ============================================================================#
        // Remediation engine configs
        // ============================================================================#
        'fallback_remediation' => Constants::REMEDIATION_CAPTCHA,
        'ordered_remediations' => [Constants::REMEDIATION_BAN, Constants::REMEDIATION_CAPTCHA],
        'fs_cache_path' => __DIR__ . '/.cache',
        'redis_dsn' => 'redis://localhost:6379',
        'memcached_dsn' => 'memcached://localhost:11211',
        'clean_ip_cache_duration' => 1,
        'bad_ip_cache_duration' => 1,
        'stream_mode' => false,
        'geolocation' => [
            'enabled' => false,
            'type' => Constants::GEOLOCATION_TYPE_MAXMIND,
            'cache_duration' => Constants::CACHE_EXPIRATION_FOR_GEO,
            'maxmind' => [
                'database_type' => Constants::MAXMIND_COUNTRY,
                'database_path' => '/some/path/GeoLite2-Country.mmdb',
            ],
        ],
    ];

    protected function setUp(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->root = vfsStream::setup('/tmp');
        $this->configs['log_directory_path'] = $this->root->url();

        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);
        $this->storage = new FileStorage();
    }

    public function testGetCapiRemediationForIpException()
    {
        $configs = array_merge($this->configs, ['use_capi' => true, 'scenarios' => ['test/test']]);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $this->storage], '', true,
            true, true, ['getRemediationEngine']);
        $bouncer->method('getRemediationEngine')->willReturn($mockRemediation);

        $this->assertInstanceOf(CapiRemediation::class, $bouncer->getRemediationEngine());

        $mockRemediation->method('getIpRemediation')->willThrowException(new \Exception('Error in unit test', 123));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->getRemediationForIp('1.2.3.3');
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(123, $errorCode);
        $this->assertEquals('Error in unit test', $errorMessage);
    }

    public function testShouldBounceCurrentIpWithCapi()
    {
        $configs = array_merge($this->configs, ['use_capi' => true, 'scenarios' => ['test/test']]);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $this->storage], '', true,
            true, true, ['getRequestUri', 'getRemediationEngine']);
        $bouncer->method('getRemediationEngine')->willReturn($mockRemediation);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(true, $result);

        $configs = array_merge($configs, ['bouncing_level' => 'bouncing_disabled']);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $this->storage], '', true,
            true, true, ['getRemediationEngine']);
        $bouncer->method('getRemediationEngine')->willReturn($mockRemediation);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(false, $result);

        $configs = array_merge($this->configs, ['use_capi' => true, 'scenarios' => ['test/test']]);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $this->storage], '', true,
            true, true, ['getRequestUri', 'getRemediationEngine']);
        $bouncer->method('getRemediationEngine')->willReturn($mockRemediation);
        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls(self::EXCLUDED_URI, '/good-uri');
        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(false, $result);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(true, $result);
    }

    public function testCacheMethodsExceptionWithCapi()
    {
        $configs = array_merge($this->configs, ['use_capi' => true, 'scenarios' => ['test/test']]);
        $mockRemediation = $this->getMockBuilder(CapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $this->storage], '', true,
            true, true, ['getRemediationEngine']);
        $bouncer->method('getRemediationEngine')->willReturn($mockRemediation);

        $this->assertInstanceOf(CapiRemediation::class, $bouncer->getRemediationEngine());

        $mockRemediation->method('pruneCache')->willThrowException(new \Exception('unit test prune cache', 123));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->pruneCache();
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(123, $errorCode);
        $this->assertEquals('Error while pruning cache: unit test prune cache', $errorMessage);

        $mockRemediation->method('clearCache')->willThrowException(new \Exception('unit test clear cache', 456));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->clearCache();
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(456, $errorCode);
        $this->assertEquals('Error while clearing cache: unit test clear cache', $errorMessage);

        $mockRemediation->method('refreshDecisions')->willThrowException(new \Exception('unit test refresh', 789));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->refreshBlocklistCache();
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(789, $errorCode);
        $this->assertEquals('Error while refreshing decisions: unit test refresh', $errorMessage);

        $mockRemediation->method('getCacheStorage')->willThrowException(new \Exception('unit test get cache storage',
            101112));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->testCacheConnection();
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(101112, $errorCode);
        $this->assertEquals('Error while testing cache connection: unit test get cache storage', $errorMessage);
    }
}
