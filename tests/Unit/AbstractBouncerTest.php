<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Unit;

/**
 * Test for templating.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSecBouncer\BouncerException;
use PHPUnit\Framework\TestCase;
use CrowdSecBouncer\AbstractBouncer;
use CrowdSecBouncer\Constants;
use CrowdSec\RemediationEngine\LapiRemediation;

/**
 * @covers \CrowdSecBouncer\AbstractBouncer::__construct
 * @covers \CrowdSecBouncer\AbstractBouncer::pruneCache
 * @covers \CrowdSecBouncer\AbstractBouncer::clearCache
 * @covers \CrowdSecBouncer\AbstractBouncer::refreshBlocklistCache
 * @covers \CrowdSecBouncer\AbstractBouncer::testCacheConnection
 *
 * @uses   \CrowdSecBouncer\AbstractBouncer::configure
 * @uses   \CrowdSecBouncer\AbstractBouncer::getConfigs
 * @uses   \CrowdSecBouncer\AbstractBouncer::getLogger
 * @covers \CrowdSecBouncer\AbstractBouncer::getRemediationEngine
 * @uses   \CrowdSecBouncer\Configuration::addBouncerNodes
 * @uses   \CrowdSecBouncer\Configuration::addCacheNodes
 * @uses   \CrowdSecBouncer\Configuration::addConnectionNodes
 * @uses   \CrowdSecBouncer\Configuration::addDebugNodes
 * @uses   \CrowdSecBouncer\Configuration::addTemplateNodes
 * @uses   \CrowdSecBouncer\Configuration::getConfigTreeBuilder
 *
 */
final class AbstractBouncerTest extends TestCase
{

    protected $configs = [
        #============================================================================#
        # Bouncer configs
        #============================================================================#
        'use_curl' => false,
        'debug_mode' => true,
        'disable_prod_log' => false,
        'log_directory_path' => __DIR__ . '/.logs',
        'display_errors' => true,
        'forced_test_ip' => '',
        'forced_test_forwarded_ip' => '',
        'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,
        'trust_ip_forward_array' => [],
        'excluded_uris' => [],
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
        #============================================================================#
        # Client configs
        #============================================================================#
        'auth_type' => Constants::AUTH_KEY,
        'tls_cert_path' => '',
        'tls_key_path' => '',
        'tls_verify_peer' => true,
        'tls_ca_cert_path' => '',
        'api_key' => 'unit-test',
        'api_url' => Constants::DEFAULT_LAPI_URL,
        'api_timeout' => 1,
        #============================================================================#
        # Remediation engine configs
        #============================================================================#
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

    public function testCacheMethodsException()
    {
        $configs = $this->configs;
        $mockRemediation = $this->getMockBuilder(LapiRemediation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pruneCache', 'clearCache', 'refreshDecisions', 'getCacheStorage'])
            ->getMock();
        $client = $this->getMockForAbstractClass(AbstractBouncer::class, [$configs, $mockRemediation]);

        $this->assertInstanceOf(LapiRemediation::class, $client->getRemediationEngine());

        $mockRemediation->method('pruneCache')->willThrowException(new \Exception('unit test prune cache', 123));

        $errorMessage = '';
        $errorCode = 0;
        try {
            $client->pruneCache();
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
            $client->clearCache();
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
            $client->refreshBlocklistCache();
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
            $client->testCacheConnection();
        } catch (BouncerException $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        $this->assertEquals(101112, $errorCode);
        $this->assertEquals('Error while testing cache connection: unit test get cache storage', $errorMessage);
    }

}
