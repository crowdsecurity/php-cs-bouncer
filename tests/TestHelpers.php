<?php

declare(strict_types=1);

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
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
    public const FRANCE = 'FR';
    public const IP_FRANCE = '78.119.253.85';



    public const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__.'/../var/phpFiles.cache';

    public const LOG_LEVEL = Logger::DEBUG; // set to Logger::DEBUG to get high verbosity

    public static function createLogger(): Logger
    {
        $log = new Logger('TESTS');
        $handler = new StreamHandler('php://stdout', self::LOG_LEVEL);
        $handler->setFormatter(new ColoredLineFormatter(null, "[%datetime%] %message% %context%\n", 'H:i:s'));
        $log->pushHandler($handler);

        return $log;
    }

    /**
     * @return array
     * @throws ErrorException
     * @throws CacheException
     */
    public static function cacheAdapterProvider(): array
    {
        // Init all adapters
        $phpFilesAdapter = new PhpFilesAdapter('php_array_adapter_backup_cache', 0, self::PHP_FILES_CACHE_ADAPTER_DIR);
        /** @var string */
        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        $redisClient = RedisAdapter::createConnection($redisCacheAdapterDsn);
        $redisAdapter = new RedisAdapter($redisClient);

        // memcached version 3.1.5 is not ready for PHP 8.1
        if (\PHP_VERSION_ID >= 80100 && version_compare(phpversion('memcached'), '3.1.5', '<=')) {
            return [
                'PhpFilesAdapter' => [$phpFilesAdapter],
                'RedisAdapter' => [$redisAdapter],
            ];
        }
        /** @var string */
        $memcachedCacheAdapterDsn = getenv('MEMCACHED_DSN');
        $memcachedAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedCacheAdapterDsn));

        return [
            'PhpFilesAdapter' => [$phpFilesAdapter],
            'RedisAdapter' => [$redisAdapter],
            'MemcachedAdapter' => [$memcachedAdapter],
        ];
    }


    public static function maxmindConfigProvider(): array
    {
        return [
            'country database' => [[
                'database_type' => 'country',
                'database_path' => __DIR__.'/GeoLite2-Country.mmdb'
            ]],
            'city database' => [[
                'database_type' => 'city',
                'database_path' => __DIR__.'/GeoLite2-City.mmdb'
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
