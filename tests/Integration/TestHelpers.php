<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Integration;

use CrowdSecBouncer\Fixes\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
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

    public const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__.'/../var/phpFiles.cache';

    public const LOG_LEVEL = Logger::DEBUG; // set to Logger::DEBUG to get high verbosity

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
    public static function cacheAdapterProvider(): array
    {
        // Init all adapters
        $phpFilesAdapter = new TagAwareAdapter(
            new PhpFilesAdapter('php_array_adapter_backup_cache', 0, self::PHP_FILES_CACHE_ADAPTER_DIR)
        );
        /** @var string */
        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        $redisClient = RedisAdapter::createConnection($redisCacheAdapterDsn);
        $redisAdapter = new RedisTagAwareAdapter($redisClient);

        /** @var string */
        $memcachedCacheAdapterDsn = getenv('MEMCACHED_DSN');
        $memcachedAdapter = new MemcachedTagAwareAdapter(
            new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedCacheAdapterDsn)));

        return [
            'PhpFilesAdapter' => [$phpFilesAdapter, 'PhpFilesAdapter'],
            'RedisAdapter' => [$redisAdapter, 'RedisAdapter'],
            'MemcachedAdapter' => [$memcachedAdapter, 'MemcachedAdapter'],
        ];
    }

    public static function maxmindConfigProvider(): array
    {
        return [
            'country database' => [[
                'database_type' => 'country',
                'database_path' => __DIR__.'/../GeoLite2-Country.mmdb',
            ]],
            'city database' => [[
                'database_type' => 'city',
                'database_path' => __DIR__.'/../GeoLite2-City.mmdb',
            ]],
        ];
    }

    public static function getLapiUrl(): string
    {
        return getenv('LAPI_URL');
    }

    public static function getBouncerKey(): string
    {
        if ($bouncerKey = getenv('BOUNCER_KEY')) {
            return $bouncerKey;
        }
        $path = realpath(__DIR__.'/../.bouncer-key');
        if (false === $path) {
            throw new RuntimeException("'.bouncer-key' file was not found.");
        }

        return file_get_contents($path);
    }
}
