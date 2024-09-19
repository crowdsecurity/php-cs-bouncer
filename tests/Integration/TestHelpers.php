<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\Constants;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Exception\CacheException;

class TestHelpers
{
    public const BAD_IP = '1.2.3.4';
    public const CLEAN_IP = '2.3.4.5';
    public const NEWLY_BAD_IP = '3.4.5.6';
    public const IP_RANGE = '24';
    public const LARGE_IPV4_RANGE = '23';
    public const BAD_IPV6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
    public const IPV6_RANGE = '64';
    public const JAPAN = 'JP';
    public const IP_JAPAN = '210.249.74.42';
    public const IP_FRANCE = '78.119.253.85';

    public const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__ . '/../var/phpFiles.cache';

    public const LOG_LEVEL = Logger::ERROR; // set to Logger::DEBUG to get high verbosity

    public static function createLogger(): Logger
    {
        $log = new Logger('TESTS');
        $handler = new StreamHandler('php://stdout', self::LOG_LEVEL);
        $handler->setFormatter(new LineFormatter("%datetime%|%level%|%context%\n"));
        $log->pushHandler($handler);

        return $log;
    }

    /**
     * @throws ErrorException
     * @throws CacheException
     */
    public static function cacheAdapterConfigProvider(): array
    {
        return [
            'PhpFilesAdapter' => [Constants::CACHE_SYSTEM_PHPFS, 'PhpFilesAdapter'],
            'RedisAdapter' => [Constants::CACHE_SYSTEM_REDIS, 'RedisAdapter'],
            'MemcachedAdapter' => [Constants::CACHE_SYSTEM_MEMCACHED, 'MemcachedAdapter'],
        ];
    }

    public static function maxmindConfigProvider(): array
    {
        return [
            'country database' => [[
                'database_type' => 'country',
                'database_path' => __DIR__ . '/../GeoLite2-Country.mmdb',
            ]],
            'city database' => [[
                'database_type' => 'city',
                'database_path' => __DIR__ . '/../GeoLite2-City.mmdb',
            ]],
        ];
    }

    public static function getLapiUrl(): string
    {
        return getenv('LAPI_URL');
    }

    public static function getAppSecUrl(): string
    {
        return getenv('APP_SEC_URL');
    }



    public static function getBouncerKey(): string
    {
        if ($bouncerKey = getenv('BOUNCER_KEY')) {
            return $bouncerKey;
        }

        return '';
    }
}
