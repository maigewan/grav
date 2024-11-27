<?php
namespace Grav\Framework\Cache\Adapter;

use DateInterval;
use Doctrine\Common\Cache\CacheProvider;
use Grav\Framework\Cache\AbstractCache;
use Grav\Framework\Cache\Exception\InvalidArgumentException;

/**
 * DoctrineCache 类
 * 
 * 此类使用 Doctrine 缓存作为后端实现，为 PSR-16 兼容的 "简单缓存" 提供支持。
 * 
 * 特性：
 * - 基于 Doctrine Cache 提供的功能。
 * - 实现了 Grav 的 `AbstractCache` 抽象类，并遵循 PSR-16 标准。
 * - 支持命名空间隔离、多键值操作、TTL 设置等功能。
 * 
 * @package Grav\Framework\Cache
 */
class DoctrineCache extends AbstractCache
{
    /** @var CacheProvider 缓存驱动 */
    protected $driver;

    /**
     * DoctrineCache 构造函数
     * 
     * @param CacheProvider $doctrineCache Doctrine 缓存驱动实例
     * @param string $namespace 缓存命名空间，用于隔离不同模块的缓存
     * @param null|int|DateInterval $defaultLifetime 默认缓存生存时间（TTL），单位为秒
     * @throws InvalidArgumentException 如果命名空间或 TTL 参数无效，则抛出异常
     * 
     * 功能：
     * - 初始化缓存实例并设置命名空间。
     * - 使用 Doctrine 提供的 `setNamespace` 方法实现命名空间隔离。
     */
    public function __construct(CacheProvider $doctrineCache, $namespace = '', $defaultLifetime = null)
    {
        try {
            // 调用父类构造函数初始化命名空间和 TTL
            parent::__construct($namespace, $defaultLifetime);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // 捕获异常并转换为自定义 InvalidArgumentException
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        // 设置 Doctrine Cache 的命名空间
        $namespace = $this->getNamespace();
        if ($namespace) {
            $doctrineCache->setNamespace($namespace);
        }

        $this->driver = $doctrineCache; // 保存缓存驱动实例
    }

    /**
     * @inheritdoc
     */
    public function doGet($key, $miss)
    {
        $value = $this->driver->fetch($key);

        // Doctrine Cache 不区分未命中和存储的 'false'，需要显式处理
        return $value !== false || $this->driver->contains($key) ? $value : $miss;
    }

    /**
     * @inheritdoc
     */
    public function doSet($key, $value, $ttl)
    {
        // 将缓存值存储到驱动，使用 TTL 设置缓存的有效期
        return $this->driver->save($key, $value, (int) $ttl);
    }

    /**
     * @inheritdoc
     */
    public function doDelete($key)
    {
        // 删除指定键的缓存值
        return $this->driver->delete($key);
    }

    /**
     * @inheritdoc
     */
    public function doClear()
    {
        // 清空所有缓存
        return $this->driver->deleteAll();
    }

    /**
     * @inheritdoc
     */
    public function doGetMultiple($keys, $miss)
    {
        // 批量获取多个键的值
        return $this->driver->fetchMultiple($keys);
    }

    /**
     * @inheritdoc
     */
    public function doSetMultiple($values, $ttl)
    {
        // 批量设置多个键值对
        return $this->driver->saveMultiple($values, (int) $ttl);
    }

    /**
     * @inheritdoc
     */
    public function doDeleteMultiple($keys)
    {
        // 批量删除多个键
        return $this->driver->deleteMultiple($keys);
    }

    /**
     * @inheritdoc
     */
    public function doHas($key)
    {
        // 检查指定键是否存在于缓存中
        return $this->driver->contains($key);
    }
}
