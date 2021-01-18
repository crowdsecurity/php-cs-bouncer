<?php

declare(strict_types=1);

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\ClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class TestHelpers
{
    const BAD_IP = '1.2.3.4';
    const CLEAN_IP = '2.3.4.5';
    const NEWLY_BAD_IP = '3.4.5.6';
    const IP_RANGE = '30';

    const FS_CACHE_ADAPTER_DIR = __DIR__.'/../var/fs.cache';
    const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__.'/../var/phpFiles.cache';

    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    const LOG_LEVEL = Logger::WARNING; // set to Logger::DEBUG to get high verbosity

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
        $memcachedCacheAdapterDsn = getenv('MEMCACHED_DSN');
        $memcachedAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedCacheAdapterDsn));

        /** @var string */
        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        /** @var ClientInterface */
        $redisClient = RedisAdapter::createConnection($redisCacheAdapterDsn);
        $redisAdapter = new RedisAdapter($redisClient);

        return [
            /*'FilesystemAdapter'  => [$fileSystemAdapter],*/
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
        $path = realpath(__DIR__.'/../.bouncer-key');
        if (false === $path) {
            throw new RuntimeException("'.bouncer-key' file was not found.");
        }
        $apiKey = file_get_contents($path);

        return $apiKey;
    }
}
