<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Predis\ClientInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class TestHelpers
{
    const TEST_IP = '1.2.3.4';
    const FS_CACHE_ADAPTER_DIR = __DIR__ . '/../var/fs.cache';
    const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__ . '/../var/phpFiles.cache';
    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    private static function delTree(string $dir): bool
    {
        if (file_exists($dir)) {
            /** @var array $items */
            $items = scandir($dir);
            $files = array_diff($items, ['.', '..']);
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
            }
            return rmdir($dir);
        }
        return true;
    }

    public static function cacheAdapterProvider(): array
    {
        // Init and clear all adapters

        $fileSystemAdapter = new FilesystemAdapter('fs_adapter_cache', 0, self::FS_CACHE_ADAPTER_DIR);
        self::delTree(self::FS_CACHE_ADAPTER_DIR);

        $phpFilesAdapter = new PhpFilesAdapter('php_array_adapter_backup_cache', 0, self::PHP_FILES_CACHE_ADAPTER_DIR);
        self::delTree(self::PHP_FILES_CACHE_ADAPTER_DIR);
        
        /** @var string */
        $memcachedCacheAdapterDsn = getenv('MEMCACHED_DSN');
        $memcachedAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedCacheAdapterDsn));
        $memcachedAdapter->clear();

        /** @var string */
        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        /** @var ClientInterface */
        $redisClient = RedisAdapter::createConnection($redisCacheAdapterDsn);
        $redisAdapter = new RedisAdapter($redisClient);
        $redisAdapter->clear();

        return [
            'FilesystemAdapter'  => [$fileSystemAdapter],
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
            'blocked_ip' => self::TEST_IP
        ];
    }

    public static function setupBasicLapiInStreamModeContext(): array
    {
        $config = self::setupBasicLapiInRuptureModeContext();
        $config['config']['rupture_mode'] = false;
        return $config;
    }
}
