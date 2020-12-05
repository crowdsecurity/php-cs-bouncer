<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Predis\ClientInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Bramus\Monolog\Formatter\ColoredLineFormatter;

class TestHelpers
{
    const BAD_IP = '1.2.3.4';
    const CLEAN_IP = '2.3.4.5';
    const FS_CACHE_ADAPTER_DIR = __DIR__ . '/../var/fs.cache';
    const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__ . '/../var/phpFiles.cache';
    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    const LOG_LEVEL = Logger::WARNING; // set to Logger::DEBUG to get high verbosity

    public static function createLogger(): Logger
    {
        $log = new Logger('TESTS');
        $handler = new StreamHandler('php://stdout', self::LOG_LEVEL);
        $handler->setFormatter(new ColoredLineFormatter(null, "[%datetime%] %message%\n", 'H:i:s'));
        $log->pushHandler($handler);
        return $log;
    }

    public static function cacheAdapterProvider(): array
    {
        // Init all adapters
        /*
        TODO P3 Failed on CI but some fixes may fix this bug. Just retry it could work! Else investigates.
        $fileSystemAdapter = new FilesystemAdapter('fs_adapter_cache', 0, self::FS_CACHE_ADAPTER_DIR);
        */

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
            'PhpFilesAdapter'  => [$phpFilesAdapter],
            'RedisAdapter'  => [$redisAdapter],
            'MemcachedAdapter'  => [$memcachedAdapter]
        ];
    }

    public static function setupBasicLapiInRuptureModeContext(): array
    {
        $apiUrl = getenv('LAPI_URL');

        $path = realpath(__DIR__ . '/../.bouncer-key');
        if ($path === false) {
            throw new RuntimeException("'.bouncer-key' file was not found.");
        }
        $apiToken = file_get_contents($path);
        return [
            'config' => ['api_token' => $apiToken, 'api_url' => $apiUrl],
            'bad_ip' => self::BAD_IP,
            'clean_ip' => self::CLEAN_IP
        ];
    }

    public static function setupBasicLapiInStreamModeContext(): array
    {
        $config = self::setupBasicLapiInRuptureModeContext();
        $config['config']['rupture_mode'] = false;
        return $config;
    }
}
