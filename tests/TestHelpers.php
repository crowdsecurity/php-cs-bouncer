<?php

declare(strict_types=1);

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\ClientInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class TestHelpers
{
    public const BAD_IP = '1.2.3.4';
    public const CLEAN_IP = '2.3.4.5';
    public const NEWLY_BAD_IP = '3.4.5.6';
    public const IP_RANGE = '24';
    public const LARGE_IPV4_RANGE = '23';
    public const BAD_IPV6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
    public const IPV6_RANGE = '64';

    public const FS_CACHE_ADAPTER_DIR = __DIR__.'/../var/fs.cache';
    public const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__.'/../var/phpFiles.cache';

    public const WATCHER_LOGIN = 'PhpUnitTestMachine';
    public const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    public const LOG_LEVEL = Logger::WARNING; // set to Logger::DEBUG to get high verbosity

    public static function createLogger(): Logger
    {
        $log = new Logger('TESTS');
        $handler = new StreamHandler('php://stdout', self::LOG_LEVEL);
        $handler->setFormatter(new ColoredLineFormatter(null, "[%datetime%] %message% %context%\n", 'H:i:s'));
        $log->pushHandler($handler);

        return $log;
    }

    public static function cacheAdapterProvider(): array
    {
        // Init all adapters

        $phpFilesAdapter = new PhpFilesAdapter('php_array_adapter_backup_cache', 0, self::PHP_FILES_CACHE_ADAPTER_DIR);

        /** @var string */
        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        /** @var ClientInterface */
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
        $apiKey = file_get_contents($path);

        return $apiKey;
    }
}
