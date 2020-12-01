<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class TestHelpers
{
    const TEST_IP = '1.2.3.4';
    const FS_CACHE_ADAPTER_DIR = __DIR__ . '/../var/fs.cache';
    const PHP_FILES_CACHE_ADAPTER_DIR = __DIR__ . '/../var/phpFiles.cache';
    const WATCHER_LOGIN = 'PhpUnitTestMachine';
    const WATCHER_PASSWORD = 'PhpUnitTestMachinePassword';

    private static function delTree($dir)
    {
        if (file_exists($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
            }
            return rmdir($dir);
        }
    }

    public static function cacheAdapterProvider(): array
    {
        // Init and clear all adapters

        $fileSystemAdapter = new FilesystemAdapter('fs_adapter_cache', 0, self::FS_CACHE_ADAPTER_DIR);
        self::delTree(self::FS_CACHE_ADAPTER_DIR);

        $phpFilesAdapter = new PhpFilesAdapter('php_array_adapter_backup_cache', 0, self::PHP_FILES_CACHE_ADAPTER_DIR);
        self::delTree(self::PHP_FILES_CACHE_ADAPTER_DIR);
        
        $memcachedCacheAdapterDsn = getenv('MEMCACHED_DSN');
        $memcachedAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedCacheAdapterDsn));
        $memcachedAdapter->clear();

        $redisCacheAdapterDsn = getenv('REDIS_DSN');
        $redisAdapter = new RedisAdapter(RedisAdapter::createConnection($redisCacheAdapterDsn));
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

        $apiToken = file_get_contents(realpath(__DIR__ . '/../.bouncer-key'));
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
