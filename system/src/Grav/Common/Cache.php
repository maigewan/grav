<?php
namespace Grav\Common;

use DirectoryIterator;
use \Doctrine\Common\Cache as DoctrineCache;
use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;
use LogicException;
use Psr\SimpleCache\CacheInterface;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function dirname;
use function extension_loaded;
use function function_exists;
use function in_array;
use function is_array;

/**
 * GravCache 对象在 Grav 中被广泛用于存储和检索缓存数据。
 * 它使用 DoctrineCache 库，并支持多种缓存机制。包括：
 *
 * APCu
 * RedisCache
 * MemCache
 * MemCacheD
 * 文件系统
 *
 * 该类提供了缓存的基本操作，如获取、保存、删除缓存项，并支持不同的缓存驱动。
 */
class Cache extends Getters
{
    /** @var string 缓存键。用于标识不同的缓存命名空间。 */
    protected $key;

    /** @var int 缓存的生命周期，以秒为单位。 */
    protected $lifetime;

    /** @var int 当前时间戳。 */
    protected $now;

    /** @var Config $config Grav 的配置对象。 */
    protected $config;

    /** @var DoctrineCache\CacheProvider 缓存驱动提供者。 */
    protected $driver;

    /** @var CacheInterface 简单缓存接口。 */
    protected $simpleCache;

    /** @var string 当前使用的缓存驱动名称。 */
    protected $driver_name;

    /** @var string 缓存驱动的设置。 */
    protected $driver_setting;

    /** @var bool 缓存是否启用。 */
    protected $enabled;

    /** @var string 缓存目录路径。 */
    protected $cache_dir;

    /** @var array 标准移除路径，用于清理特定类型的缓存。 */
    protected static $standard_remove = [
        'cache://twig/',
        'cache://doctrine/',
        'cache://compiled/',
        'cache://clockwork/',
        'cache://validated-',
        'cache://images',
        'asset://',
    ];

    /** @var array 标准移除路径（不包括图像缓存）。 */
    protected static $standard_remove_no_images = [
        'cache://twig/',
        'cache://doctrine/',
        'cache://compiled/',
        'cache://clockwork/',
        'cache://validated-',
        'asset://',
    ];

    /** @var array 所有类型的缓存移除路径。 */
    protected static $all_remove = [
        'cache://',
        'cache://images',
        'asset://',
        'tmp://'
    ];

    /** @var array 仅移除资产缓存的路径。 */
    protected static $assets_remove = [
        'asset://'
    ];

    /** @var array 仅移除图像缓存的路径。 */
    protected static $images_remove = [
        'cache://images'
    ];

    /** @var array 仅移除缓存文件的路径。 */
    protected static $cache_remove = [
        'cache://'
    ];

    /** @var array 仅移除临时文件的路径。 */
    protected static $tmp_remove = [
        'tmp://'
    ];

    /**
     * 构造函数
     *
     * @param Grav $grav Grav 实例
     */
    public function __construct(Grav $grav)
    {
        $this->init($grav);
    }

    /**
     * 初始化方法，设置基础键和根据配置设置的驱动
     *
     * @param  Grav $grav Grav 实例
     * @return void
     */
    public function init(Grav $grav)
    {
        $this->config = $grav['config'];
        $this->now = time();

        // 根据配置决定缓存是否启用
        if (null === $this->enabled) {
            $this->enabled = (bool)$this->config->get('system.cache.enabled');
        }

        /** @var Uri $uri */
        $uri = $grav['uri'];

        // 获取缓存前缀
        $prefix = $this->config->get('system.cache.prefix');
        // 生成唯一性标识，用于缓存键
        $uniqueness = substr(md5($uri->rootUrl(true) . $this->config->key() . GRAV_VERSION), 2, 8);

        // 缓存键允许我们在配置更改时使所有缓存失效
        $this->key = ($prefix ?: 'g') . '-' . $uniqueness;
        // 确定缓存目录
        $this->cache_dir = $grav['locator']->findResource('cache://doctrine/' . $uniqueness, true, true);
        // 获取缓存驱动设置
        $this->driver_setting = $this->config->get('system.cache.driver');
        // 获取实际的缓存驱动
        $this->driver = $this->getCacheDriver();
        // 设置驱动的命名空间为当前缓存键
        $this->driver->setNamespace($this->key);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = Grav::instance()['events'];
        // 监听调度器初始化事件，以便添加定时任务
        $dispatcher->addListener('onSchedulerInitialized', [$this, 'onSchedulerInitialized']);
    }

    /**
     * 获取简单缓存接口实例
     *
     * @return CacheInterface
     */
    public function getSimpleCache()
    {
        if (null === $this->simpleCache) {
            // 创建 DoctrineCache 适配器实例，并设置生命周期
            $cache = new \Grav\Framework\Cache\Adapter\DoctrineCache($this->driver, '', $this->getLifetime());

            // 禁用缓存键验证
            $cache->setValidation(false);

            $this->simpleCache = $cache;
        }

        return $this->simpleCache;
    }

    /**
     * 删除旧的过时的基于文件的缓存
     *
     * @return int 删除的缓存文件夹数量
     */
    public function purgeOldCache()
    {
        $cache_dir = dirname($this->cache_dir);
        $current = Utils::basename($this->cache_dir);
        $count = 0;

        // 遍历缓存目录中的所有文件夹
        foreach (new DirectoryIterator($cache_dir) as $file) {
            $dir = $file->getBasename();
            // 跳过当前缓存目录、点目录和文件
            if ($dir === $current || $file->isDot() || $file->isFile()) {
                continue;
            }

            // 删除缓存文件夹
            Folder::delete($file->getPathname());
            $count++;
        }

        return $count;
    }

    /**
     * 公共访问器，用于设置缓存的启用状态
     *
     * @param bool|int $enabled 是否启用缓存
     * @return void
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * 获取当前缓存的启用状态
     *
     * @return bool 是否启用缓存
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * 获取缓存状态信息
     *
     * @return string 缓存状态的描述信息
     */
    public function getCacheStatus()
    {
        return 'Cache: [' . ($this->enabled ? 'true' : 'false') . '] Setting: [' . $this->driver_setting . '] Driver: [' . $this->driver_name . ']';
    }

    /**
     * 自动选择要使用的缓存机制。如果手动选择某个驱动，它将使用该驱动。
     * 如果配置中没有指定驱动，或者设置为 'auto'，它将根据已安装的缓存扩展选择最佳选项。
     *
     * @return DoctrineCache\CacheProvider  使用的缓存驱动
     * @throws LogicException 如果所需的 PHP 扩展未安装
     */
    public function getCacheDriver()
    {
        $setting = $this->driver_setting;
        $driver_name = 'file'; // 默认驱动为文件系统

        // CLI 兼容性要求使用非易失性缓存驱动
        if ($this->config->get('system.cache.cli_compatibility') && (
            $setting === 'auto' || $this->isVolatileDriver($setting))) {
            $setting = $driver_name;
        }

        // 如果未指定驱动或设置为自动，根据扩展加载情况选择驱动
        if (!$setting || $setting === 'auto') {
            if (extension_loaded('apcu')) {
                $driver_name = 'apcu';
            } elseif (extension_loaded('wincache')) {
                $driver_name = 'wincache';
            }
        } else {
            $driver_name = $setting;
        }

        $this->driver_name = $driver_name;

        // 根据驱动名称实例化对应的缓存驱动
        switch ($driver_name) {
            case 'apc':
            case 'apcu':
                $driver = new DoctrineCache\ApcuCache();
                break;

            case 'wincache':
                $driver = new DoctrineCache\WinCacheCache();
                break;

            case 'memcache':
                if (extension_loaded('memcache')) {
                    $memcache = new \Memcache();
                    $memcache->connect(
                        $this->config->get('system.cache.memcache.server', 'localhost'),
                        $this->config->get('system.cache.memcache.port', 11211)
                    );
                    $driver = new DoctrineCache\MemcacheCache();
                    $driver->setMemcache($memcache);
                } else {
                    throw new LogicException('Memcache PHP 扩展未安装');
                }
                break;

            case 'memcached':
                if (extension_loaded('memcached')) {
                    $memcached = new \Memcached();
                    $memcached->addServer(
                        $this->config->get('system.cache.memcached.server', 'localhost'),
                        $this->config->get('system.cache.memcached.port', 11211)
                    );
                    $driver = new DoctrineCache\MemcachedCache();
                    $driver->setMemcached($memcached);
                } else {
                    throw new LogicException('Memcached PHP 扩展未安装');
                }
                break;

            case 'redis':
                if (extension_loaded('redis')) {
                    $redis = new \Redis();
                    $socket = $this->config->get('system.cache.redis.socket', false);
                    $password = $this->config->get('system.cache.redis.password', false);
                    $databaseId = $this->config->get('system.cache.redis.database', 0);

                    // 通过套接字或主机连接 Redis
                    if ($socket) {
                        $redis->connect($socket);
                    } else {
                        $redis->connect(
                            $this->config->get('system.cache.redis.server', 'localhost'),
                            $this->config->get('system.cache.redis.port', 6379)
                        );
                    }

                    // 如果设置了密码，进行认证
                    if ($password && !$redis->auth($password)) {
                        throw new \RedisException('Redis 认证失败');
                    }

                    // 如果设置了非默认数据库 ID，选择对应的数据库
                    if ($databaseId && !$redis->select($databaseId)) {
                        throw new \RedisException('无法选择 Redis 的备用数据库 ID');
                    }

                    $driver = new DoctrineCache\RedisCache();
                    $driver->setRedis($redis);
                } else {
                    throw new LogicException('Redis PHP 扩展未安装');
                }
                break;

            default:
                // 默认使用文件系统缓存
                $driver = new DoctrineCache\FilesystemCache($this->cache_dir);
                break;
        }

        return $driver;
    }

    /**
     * 根据 ID 获取缓存项。如果不存在，则返回 false
     *
     * @param  string $id 缓存项的 ID
     * @return mixed|bool 缓存的数据，可以是任意类型，或不存在时返回 false
     */
    public function fetch($id)
    {
        if ($this->enabled) {
            return $this->driver->fetch($id);
        }

        return false;
    }

    /**
     * 存储一个新的缓存项
     *
     * @param  string       $id       缓存项的 ID
     * @param  array|object|int $data     要存储的缓存数据
     * @param  int|null     $lifetime 缓存的生命周期，单位为秒
     */
    public function save($id, $data, $lifetime = null)
    {
        if ($this->enabled) {
            if ($lifetime === null) {
                $lifetime = $this->getLifetime();
            }
            $this->driver->save($id, $data, $lifetime);
        }
    }

    /**
     * 根据 ID 删除缓存项
     *
     * @param string $id 缓存数据项的 ID
     * @return bool 如果成功删除则返回 true
     */
    public function delete($id)
    {
        if ($this->enabled) {
            return $this->driver->delete($id);
        }

        return false;
    }

    /**
     * 删除所有缓存
     *
     * @return bool 如果成功删除则返回 true
     */
    public function deleteAll()
    {
        if ($this->enabled) {
            return $this->driver->deleteAll();
        }

        return false;
    }

    /**
     * 判断缓存中是否存在指定 ID 的项
     *
     * @param string $id 缓存数据项的 ID
     * @return bool 如果缓存中存在该项则返回 true
     */
    public function contains($id)
    {
        if ($this->enabled) {
            return $this->driver->contains(($id));
        }

        return false;
    }

    /**
     * 获取当前的缓存键
     *
     * @return string 缓存键
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * 设置缓存键（高级用法）
     *
     * @param string $key 新的缓存键
     * @return void
     */
    public function setKey($key)
    {
        $this->key = $key;
        $this->driver->setNamespace($this->key);
    }

    /**
     * 辅助方法，用于清除所有 Grav 缓存
     *
     * @param string $remove 清除类型：standard|all|assets-only|images-only|cache-only
     * @return array 清除操作的输出信息
     */
    public static function clearCache($remove = 'standard')
    {
        $locator = Grav::instance()['locator'];
        $output = [];
        $user_config = USER_DIR . 'config/system.yaml';

        // 根据清除类型选择要移除的路径
        switch ($remove) {
            case 'all':
                $remove_paths = self::$all_remove;
                break;
            case 'assets-only':
                $remove_paths = self::$assets_remove;
                break;
            case 'images-only':
                $remove_paths = self::$images_remove;
                break;
            case 'cache-only':
                $remove_paths = self::$cache_remove;
                break;
            case 'tmp-only':
                $remove_paths = self::$tmp_remove;
                break;
            case 'invalidate':
                $remove_paths = [];
                break;
            default:
                if (Grav::instance()['config']->get('system.cache.clear_images_by_default')) {
                    $remove_paths = self::$standard_remove;
                } else {
                    $remove_paths = self::$standard_remove_no_images;
                }
        }

        // 如果需要，删除 Doctrine 缓存中的所有条目
        if (in_array($remove, ['all', 'standard'])) {
            $cache = Grav::instance()['cache'];
            $cache->driver->deleteAll();
        }

        // 触发清除缓存前的事件，允许扩展添加自定义的清除路径
        Grav::instance()->fireEvent('onBeforeCacheClear', new Event(['remove' => $remove, 'paths' => &$remove_paths]));

        // 遍历所有要移除的路径并执行删除操作
        foreach ($remove_paths as $stream) {
            // 将流转换为实际路径
            try {
                $path = $locator->findResource($stream, true, true);
                if ($path === false) {
                    continue;
                }

                $anything = false;
                $files = glob($path . '/*');

                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (is_link($file)) {
                            // 跳过符号链接
                            $output[] = '<yellow>跳过符号链接:  </yellow>' . $file;
                        } elseif (is_file($file)) {
                            // 尝试删除文件
                            if (@unlink($file)) {
                                $anything = true;
                            }
                        } elseif (is_dir($file)) {
                            // 尝试删除目录
                            if (Folder::delete($file, false)) {
                                $anything = true;
                            }
                        }
                    }
                }

                if ($anything) {
                    $output[] = '<red>已清除:  </red>' . $path . '/*';
                }
            } catch (Exception $e) {
                // 处理流未找到或删除文件时的错误
                $output[] = '<red>错误: </red>' . $e->getMessage();
            }
        }

        $output[] = '';

        // 如果清除类型为 'all' 或 'standard'，触碰用户配置文件以使缓存失效
        if (($remove === 'all' || $remove === 'standard') && file_exists($user_config)) {
            touch($user_config);

            $output[] = '<red>已触碰: </red>' . $user_config;
            $output[] = '';
        }

        // 清除 PHP 的文件状态缓存
        @clearstatcache();

        // 如果启用了 OPcache，重置 OPcache
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // 触发清除缓存后的事件，允许扩展进行后续操作
        Grav::instance()->fireEvent('onAfterCacheClear', new Event(['remove' => $remove, 'output' => &$output]));

        return $output;
    }

    /**
     * 使缓存失效，通过触碰用户配置文件并清除 PHP 的缓存
     *
     * @return void
     */
    public static function invalidateCache()
    {
        $user_config = USER_DIR . 'config/system.yaml';

        // 触碰用户配置文件以使缓存失效
        if (file_exists($user_config)) {
            touch($user_config);
        }

        // 清除 PHP 的文件状态缓存
        @clearstatcache();

        // 如果启用了 OPcache，重置 OPcache
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /**
     * 以编程方式设置缓存的生命周期
     *
     * @param int $future 未来的时间戳
     * @return void
     */
    public function setLifetime($future)
    {
        if (!$future) {
            return;
        }

        // 计算剩余时间
        $interval = (int)($future - $this->now);
        // 如果剩余时间在有效范围内，则更新生命周期
        if ($interval > 0 && $interval < $this->getLifetime()) {
            $this->lifetime = $interval;
        }
    }

    /**
     * 获取缓存的生命周期（以秒为单位）
     *
     * @return int 缓存生命周期
     */
    public function getLifetime()
    {
        if ($this->lifetime === null) {
            // 从配置中获取缓存生命周期，默认为一周（604800 秒）
            $this->lifetime = (int)($this->config->get('system.cache.lifetime') ?: 604800);
        }

        return $this->lifetime;
    }

    /**
     * 获取当前使用的缓存驱动名称
     *
     * @return string 缓存驱动名称
     */
    public function getDriverName()
    {
        return $this->driver_name;
    }

    /**
     * 获取当前的缓存驱动设置
     *
     * @return string 缓存驱动设置
     */
    public function getDriverSetting()
    {
        return $this->driver_setting;
    }

    /**
     * 判断给定的驱动是否是易失性的，即是否驻留在 PHP 进程内存中
     *
     * @param string $setting 缓存驱动设置
     * @return bool 如果是易失性驱动则返回 true
     */
    public function isVolatileDriver($setting)
    {
        return in_array($setting, ['apc', 'apcu', 'xcache', 'wincache'], true);
    }

    /**
     * 静态方法，用作调度任务以清除旧的 Doctrine 文件
     *
     * @param bool $echo 是否输出信息
     *
     * @return string|void 输出的消息或无
     */
    public static function purgeJob($echo = false)
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];
        $deleted_folders = $cache->purgeOldCache();
        $msg = '已清除 ' . $deleted_folders . ' 个旧的缓存文件夹...';

        if ($echo) {
            echo $msg;
        } else {
            return $msg;
        }
    }

    /**
     * 静态方法，用作调度任务以清除 Grav 缓存
     *
     * @param string $type 清除类型
     * @return void
     */
    public static function clearJob($type)
    {
        $result = static::clearCache($type);
        static::invalidateCache();

        echo strip_tags(implode("\n", $result));
    }

    /**
     * 监听调度器初始化事件，添加缓存清理的定时任务
     *
     * @param Event $event 事件对象
     * @return void
     */
    public function onSchedulerInitialized(Event $event)
    {
        /** @var Scheduler $scheduler */
        $scheduler = $event['scheduler'];
        $config = Grav::instance()['config'];

        // 设置文件缓存清理的定时任务
        $at = $config->get('system.cache.purge_at');
        $name = 'cache-purge';
        $logs = 'logs/' . $name . '.out';

        $job = $scheduler->addFunction('Grav\Common\Cache::purgeJob', [true], $name);
        $job->at($at);
        $job->output($logs);
        $job->backlink('/config/system#caching');

        // 设置缓存清理的定时任务
        $at = $config->get('system.cache.clear_at');
        $clear_type = $config->get('system.cache.clear_job_type');
        $name = 'cache-clear';
        $logs = 'logs/' . $name . '.out';

        $job = $scheduler->addFunction('Grav\Common\Cache::clearJob', [$clear_type], $name);
        $job->at($at);
        $job->output($logs);
        $job->backlink('/config/system#caching');
    }
}
