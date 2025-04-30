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
use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\LapiClient\Constants as LapiConstants;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
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
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationForIp
 * @covers \CrowdSecBouncer\AbstractBouncer::getTrustForwardedIpBoundsList
 * @covers \CrowdSecBouncer\AbstractBouncer::handleForwardedFor
 * @covers \CrowdSecBouncer\AbstractBouncer::shouldUseAppSec
 * @covers \CrowdSecBouncer\AbstractBouncer::buildRequestRawBody
 *
 * @uses   \CrowdSecBouncer\AbstractBouncer::handleRemediation
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
 *
 * @uses \CrowdSecBouncer\Helper::buildFormData
 * @uses \CrowdSecBouncer\Helper::buildRawBodyFromSuperglobals
 * @uses \CrowdSecBouncer\Helper::extractBoundary
 * @uses \CrowdSecBouncer\Helper::getMultipartRawBody
 * @uses \CrowdSecBouncer\Helper::getRawInput
 * @uses \CrowdSecBouncer\Helper::readStream
 * @uses \CrowdSecBouncer\Helper::appendFileData
 * @uses \CrowdSecBouncer\AbstractBouncer::handleBounceExclusion
 *
 * @covers \CrowdSecBouncer\AbstractBouncer::pushUsageMetrics
 * @covers \CrowdSecBouncer\AbstractBouncer::hasBaasUri
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
        'api_url' => LapiConstants::DEFAULT_LAPI_URL,
        'appsec_url' => LapiConstants::DEFAULT_APPSEC_URL,
        'use_appsec' => false,
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

    public const BOUNCER_NAME = 'bouncer-lib-unit-test';
    public const BOUNCER_VERSION = 'v0.0.0';
    public const BOUNCER_TYPE = 'crowdsec-test-php-bouncer';

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
        if (\PHP_VERSION_ID >= 80400) {
            // Retrieve the current error reporting level
            $originalErrorReporting = error_reporting();
            // Suppress deprecated warnings temporarily
            // We do this because of
            // Deprecated: Gregwar\Captcha\CaptchaBuilder::__construct(): Implicitly marking parameter $builder as nullable
            // is deprecated, the explicit nullable type must be used instead
            error_reporting($originalErrorReporting & ~\E_DEPRECATED);
        }

        // shouldUseAppSec
        // Test with TLS
        $configs = array_merge($this->configs, [
            'auth_type' => 'tls',
            'tls_cert_path' => 'some_value',
            'tls_key_path' => 'some_value',
            'tls_ca_cert_path' => 'some_value',
            'tls_verify_peer' => true,
            'use_appsec' => true]);
        $client = new BouncerClient($configs);
        $cache = new PhpFiles($configs);
        $lapiRemediation = new LapiRemediation($configs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $lapiRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldUseAppSec',
            ['bypass']
        );
        $this->assertEquals(false, $result, 'AppSec should not be used with TLS');
        // Test OK if bypass
        $configs = array_merge($this->configs, [
            'use_appsec' => true,
        ]);
        $client = new BouncerClient($configs);
        $cache = new PhpFiles($configs);
        $lapiRemediation = new LapiRemediation($configs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $lapiRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldUseAppSec',
            ['bypass']
        );
        $this->assertEquals(true, $result, 'AppSec should be used if bypass');
        // Test if not bypass
        $configs = array_merge($this->configs, [
            'use_appsec' => true,
        ]);
        $client = new BouncerClient($configs);
        $cache = new PhpFiles($configs);
        $lapiRemediation = new LapiRemediation($configs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $lapiRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'shouldUseAppSec',
            ['somevalue']
        );
        $this->assertEquals(false, $result, 'AppSec should not be used if ban');

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

        if (\PHP_VERSION_ID >= 80400 && isset($originalErrorReporting)) {
            // Restore the original error reporting level
            error_reporting($originalErrorReporting);
        }
    }

    /**
     * @group test
     */
    public function testBuildRequestRawbody()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfig'])
            ->getMock();

        $mockRemediation->method('getConfig')->willReturnOnConsecutiveCalls(
            null, // Return null on the first call
            1     // Return 1 on all subsequent calls
        );

        // test 1: bad resource
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [1024, 'bad-resource']
        );
        $this->assertEquals('', $result, 'Should return an empty string for unvalid resource');

        // Test 2: resource is a ok (and use default value for appsec_max_body_size_kb)
        $mockRemediation->method('getConfig')->will(
            $this->returnValueMap(
                [
                    ['appsec_max_body_size_kb', 1],
                ]
            )
        );
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [$inputStream]
        );

        $this->assertEquals('{"key": "value"}', $result, 'Should return the content of the stream');
        // Test 3: multipart/form-data (and use 1 for appsec_max_body_size_kb)
        $mockRemediation->method('getConfig')->will(
            $this->returnValueMap(
                [
                    ['appsec_max_body_size_kb', null],
                ]
            )
        );
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary="----WebKitFormBoundary7MA4YWxkTrZu0gW"';
        $_POST = ['key' => 'value'];
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [$inputStream]
        );

        $expected = <<<EOF
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="key"

value
------WebKitFormBoundary7MA4YWxkTrZu0gW--

EOF;
        $expected = str_replace("\n", "\r\n", $expected);

        $this->assertEquals($expected, $result, 'Should return the posted data');

        // Test 4: multipart with no boundary
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [$inputStream]
        );

        $this->assertEquals('', $result, 'Should return empty as there is no usable boundary');

        // Test 5: multipart with one file
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);

        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW; charset=UTF-8';
        $_POST = [];

        file_put_contents($this->root->url() . '/tmp1', 'THIS_IS_THE_FILE_1_CONTENT');

        $_FILES = [
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $this->root->url() . '/tmp1',
                'error' => 0,
                'size' => 1024,
            ],
        ];

        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [$inputStream]
        );

        $expected = <<<EOF
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="file"; filename="test.txt"
Content-Type: text/plain

THIS_IS_THE_FILE_1_CONTENT
------WebKitFormBoundary7MA4YWxkTrZu0gW--

EOF;

        $expected = str_replace("\n", "\r\n", $expected);
        $this->assertEquals($expected, $result, 'Should return the data with the file');
        // Test 6: multipart with multiple files
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW';
        $_POST = [];
        file_put_contents($this->root->url() . '/tmp1', 'THIS_IS_THE_FILE_1_CONTENT');
        file_put_contents($this->root->url() . '/tmp2', 'THIS_IS_THE_FILE_2_CONTENT');
        file_put_contents($this->root->url() . '/tmp3', 'THIS_IS_THE_FILE_3_CONTENT');
        $_FILES = [
            'file' => [
                'name' => [
                    0 => 'image1.jpg',
                    1 => 'image2.jpg',
                    2 => 'image3.png',
                ],
                'type' => [
                    0 => 'image/jpeg',
                    1 => 'image/jpeg',
                    2 => 'image/png',
                ],
                'tmp_name' => [
                    0 => $this->root->url() . '/tmp1',
                    1 => $this->root->url() . '/tmp2',
                    2 => $this->root->url() . '/tmp3',
                ],
                'error' => [
                    0 => 0,
                    1 => 0,
                    2 => 0,
                ],
                'size' => [
                    0 => 12345,
                    1 => 54321,
                    2 => 67890,
                ],
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $bouncer,
            'buildRequestRawBody',
            [$inputStream]
        );

        $expected = <<<EOF
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="file"; filename="image1.jpg"
Content-Type: image/jpeg

THIS_IS_THE_FILE_1_CONTENT
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="file"; filename="image2.jpg"
Content-Type: image/jpeg

THIS_IS_THE_FILE_2_CONTENT
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="file"; filename="image3.png"
Content-Type: image/png

THIS_IS_THE_FILE_3_CONTENT
------WebKitFormBoundary7MA4YWxkTrZu0gW--

EOF;
        $expected = str_replace("\n", "\r\n", $expected);

        $this->assertEquals($expected, $result, 'Should return the data with the files');
    }

    public function testGetRemediationForIpException()
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

    public function testPushUsageMetricsException()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pushUsageMetrics'])
            ->getMock();
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $this->assertInstanceOf(LapiRemediation::class, $bouncer->getRemediationEngine());

        $mockRemediation->method('pushUsageMetrics')->willThrowException(new \Exception('Error in unit test', 123));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $bouncer->pushUsageMetrics(self::BOUNCER_NAME, self::BOUNCER_VERSION, self::BOUNCER_TYPE);
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(123, $errorCode);
        $this->assertEquals('Error in unit test', $errorMessage);
    }

    public function testHasBlockAsAServiceUri()
    {
        $configs = $this->configs;
        $client = new BouncerClient($configs);
        $cache = new PhpFiles($configs);
        $lapiRemediation = new LapiRemediation($configs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $lapiRemediation]);

        $result = $bouncer->hasBaasUri();
        $this->assertEquals(false, $result);

        $configs = array_merge($this->configs, [
            'api_url' => 'https://admin.api.crowdsec.net',
        ]);

        $client = new BouncerClient($configs);
        $cache = new PhpFiles($configs);
        $lapiRemediation = new LapiRemediation($configs, $client, $cache);
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $lapiRemediation]);

        $result = $bouncer->hasBaasUri();
        $this->assertEquals(true, $result);
    }

    public function testShouldBounceCurrentIp()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation', 'getConfig', 'updateMetricsOriginsCount'])
            ->getMock();
        $mockRemediation->method('getConfig')->willReturnOnConsecutiveCalls(
            'normal_bouncing' // Return normal_bouncing on the first call
        );

        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(true, $result);

        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation', 'getConfig', 'updateMetricsOriginsCount'])
            ->getMock();
        $mockRemediation->method('getConfig')->willReturnOnConsecutiveCalls(
            'bouncing_disabled'
        );
        $bouncer = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $result = $bouncer->shouldBounceCurrentIp();
        $this->assertEquals(false, $result);

        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIpRemediation', 'getConfig', 'updateMetricsOriginsCount'])
            ->getMock();
        $mockRemediation->method('getConfig')->willReturnOnConsecutiveCalls(
            'normal_bouncing',
            'normal_bouncing'
        );
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
