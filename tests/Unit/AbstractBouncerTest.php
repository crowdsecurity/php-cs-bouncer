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

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\BouncerException;
use CrowdSecBouncer\Constants;
use CrowdSecBouncer\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::__construct
 * @covers \CrowdSecBouncer\AbstractBouncer::configure
 * @covers \CrowdSecBouncer\AbstractBouncer::getConfig
 * @covers \CrowdSecBouncer\AbstractBouncer::getConfigs
 * @covers \CrowdSecBouncer\AbstractBouncer::getLogger
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationEngine
 * @covers \CrowdSecBouncer\AbstractBouncer::handleCache
 * @covers \CrowdSecBouncer\AbstractBouncer::handleClient
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
 * @uses \CrowdSecBouncer\AbstractBouncer::handleRemediation
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldTrustXforwardedFor
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldBounceCurrentIp
 * @covers \CrowdSecBouncer\AbstractBouncer::checkCaptcha
 * @covers \CrowdSecBouncer\AbstractBouncer::buildCaptchaCouple
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
final class AbstractBouncerTest extends TestCase
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
    }

    public function testPrivateAndProtectedMethods()
    {
        // shouldNotCheckResolution
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation], '', true,
            true, true, ['getHttpMethod', 'getPostedVariable']);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => false]]
        );
        // has_to_be_resolved = false
        $this->assertEquals(true, $result);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => null]]
        );
        // has_to_be_resolved = null
        $this->assertEquals(true, $result);

        $bouncer->method('getHttpMethod')->willReturnOnConsecutiveCalls('POST', 'GET');
        $bouncer->method('getPostedVariable')->willReturnOnConsecutiveCalls('1');

        // has_to_be_resolved = true and POST method and crowdsec_captcha = 1
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => true]]
        );
        $this->assertEquals(false, $result);
        // has_to_be_resolved = true and POST method and captcha_variable = null
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => null]]
        );
        $this->assertEquals(true, $result);

        // has_to_be_resolved = true and GET method
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldNotCheckResolution',
            [['has_to_be_resolved' => true]]
        );
        $this->assertEquals(true, $result);

        // Classic tests
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation], '', true,
            true, true, ['getHttpRequestHeader']);

        $bouncer->method('getHttpRequestHeader')->willReturnOnConsecutiveCalls('1.2.3.4', '1.2.3.4');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['4.5.6.7', $configs]
        );
        // 4.5.6.7 is not a trusted ip, so the result is passed ip
        $this->assertEquals('4.5.6.7', $result);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['5.6.7.8', $configs]
        );
        // 5.6.7.8 is a trusted ip, so the result is the forwarded ip
        $this->assertEquals('1.2.3.4', $result);

        // Test disabled
        $configs = array_merge($this->configs, ['forced_test_forwarded_ip' => 'no_forward']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation], '', true,
            true, true, ['getHttpRequestHeader']);

        $bouncer->method('getHttpRequestHeader')->willReturnOnConsecutiveCalls('1.2.3.4', '1.2.3.4');
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['4.5.6.7', $configs]
        );
        // 4.5.6.7 is not a trusted ip, so the result is passed ip
        $this->assertEquals('4.5.6.7', $result);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['5.6.7.8', $configs]
        );
        // 5.6.7.8 is a trusted ip, so the result should be the forwarded ip but the setting is disabled
        $this->assertEquals('5.6.7.8', $result);

        // Test force forwarded ip
        $configs = array_merge($this->configs, ['forced_test_forwarded_ip' => '120.130.140.150']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation], '', true,
            true, true, ['getHttpRequestHeader']);

        $bouncer->method('getHttpRequestHeader')->willReturnOnConsecutiveCalls('1.2.3.4', '1.2.3.4');
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['4.5.6.7', $configs]
        );
        // 4.5.6.7 is not a trusted ip so the result is the passed ip
        $this->assertEquals('4.5.6.7', $result);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'handleForwardedFor',
            ['5.6.7.8', $configs]
        );
        // 5.6.7.8 is a trusted ip, so the result should be the forwarded ip but the setting is a forced ip
        $this->assertEquals('120.130.140.150', $result);

        // getTrustForwardedIpBoundsList
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getTrustForwardedIpBoundsList',
            []
        );
        $this->assertEquals([['005.006.007.008', '005.006.007.008']], $result);

        // capRemediationLevel
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $this->assertInstanceOf(LapiRemediation::class, $bouncer->getRemediationEngine());

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('ban', $result, 'Remediation should be capped as ban');

        $this->configs['bouncing_level'] = Constants::BOUNCING_LEVEL_DISABLED;
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage', 'getConfig'])
            ->getMock();
        $mockRemediation->method('getConfig')->will(
            $this->returnValueMap(
                [
                    ['ordered_remediations', ['ban', 'captcha', 'bypass']],
                ]
            )
        );

        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('bypass', $result, 'Remediation should be capped as bypass');

        $this->configs['bouncing_level'] = Constants::BOUNCING_LEVEL_FLEX;
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage', 'getConfig'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);
        $mockRemediation->method('getConfig')->will(
            $this->returnValueMap(
                [
                    ['ordered_remediations', ['ban', 'captcha', 'bypass']],
                ]
            )
        );
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('captcha', $result, 'Remediation should be capped as captcha');

        // checkCaptcha
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'test2', '5.6.7.8']
        );
        $this->assertEquals(false, $result, 'Captcha should be marked as not resolved');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'test1', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['test1', 'TEST1', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved even for case non matching');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'checkCaptcha',
            ['001', 'ool', '5.6.7.8']
        );
        $this->assertEquals(true, $result, 'Captcha should be marked as resolved even for some similar chars');

        // buildCaptchaCouple
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildCaptchaCouple',
            []
        );

        $this->assertArrayHasKey('phrase', $result, 'Captcha couple should have a phrase');
        $this->assertArrayHasKey('inlineImage', $result, 'Captcha couple should have a inlineImage');

        $this->assertIsString($result['phrase'], 'Captcha phrase should be ok');
        $this->assertEquals(5, strlen($result['phrase']), 'Captcha phrase should be of length 5');

        $this->assertStringStartsWith('data:image/jpeg;base64', $result['inlineImage'], 'Captcha image should be ok');

        // getCache
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCache',
            []
        );

        $this->assertInstanceOf(\CrowdSec\RemediationEngine\CacheStorage\AbstractCache::class, $result, 'Get cache should return remediation cache');
        // getBanHtml
        $this->configs = array_merge($this->configs, [
            'text' => [
                'ban_wall' => [
                    'title' => 'BAN TEST TITLE',
                ],
            ],
        ]);
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getBanHtml',
            []
        );
        $this->assertStringContainsString('<h1>BAN TEST TITLE</h1>', $result, 'Ban rendering should be as expected');

        // getCaptchaHtml
        $this->configs = array_merge($this->configs, [
            'text' => [
                'captcha_wall' => [
                    'title' => 'CAPTCHA TEST TITLE',
                ],
            ],
        ]);
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCaptchaHtml',
            [false, 'fake-inline-image', 'fake-url']
        );
        $this->assertStringContainsString('CAPTCHA TEST TITLE', $result, 'Captcha rendering should be as expected');
        $this->assertStringNotContainsString('<p class="error">', $result, 'Should be no error message');

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'getCaptchaHtml',
            [true, 'fake-inline-image', 'fake-url']
        );
        $this->assertStringContainsString('<p class="error">', $result, 'Should be no error message');

        // shouldTrustXforwardedFor
        unset($_POST['crowdsec_captcha']);
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldTrustXforwardedFor',
            ['not-an-ip']
        );
        $this->assertEquals(false, $result, 'Should return false if ip is invalid');
    }

    public function testGetRemediationForIpExeption()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $this->assertInstanceOf(LapiRemediation::class, $bouncer->getRemediationEngine());

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

    public function testShouldBounceCurrentIp()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(true, $result);

        $configs = array_merge($this->configs, ['bouncing_level' => 'bouncing_disabled']);
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(false, $result);

        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation], '', true,
            true, true, ['getRequestUri']);

        $bouncer->method('getRequestUri')->willReturnOnConsecutiveCalls(self::EXCLUDED_URI, '/good-uri');
        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(false, $result);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(true, $result);
    }

    public function testCacheMethodsException()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $this->assertInstanceOf(LapiRemediation::class, $bouncer->getRemediationEngine());

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
