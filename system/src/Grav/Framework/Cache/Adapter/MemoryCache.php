<?php
namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;
use function array_key_exists;

/**
 * MemoryCache 类
 * 
 * 此类为 PSR-16 兼容的 "简单缓存" 提供基于内存的实现。
 * 
 * 特性：
 * - 使用 PHP 的数组作为内存缓存后端。
 * - 缓存数据仅在当前请求的生命周期内有效。
 * - 不支持命名空间或默认 TTL，因为每个缓存实例是独立的。
 * 
 * 使用场景：
 * - 适合需要短生命周期、高性能的临时缓存（如存储请求期间生成的数据）。
 * 
 * @package Grav\Framework\Cache
 */
class MemoryCache extends AbstractCache
{
    /** @var array 内存缓存存储 */
    protected $cache = [];

    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $miss 如果键不存在时返回的默认值
     * @return mixed 返回缓存值或默认值
     */
    public function doGet($key, $miss)
    {
        if (!array_key_exists($key, $this->cache)) {
            return $miss;
        }

        return $this->cache[$key];
    }

    /**
     * 设置缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 生存时间（TTL），此实现中 TTL 被忽略
     * @return bool 成功返回 true
     */
    public function doSet($key, $value, $ttl)
    {
        $this->cache[$key] = $value;

        return true;
    }

    /**
     * 删除缓存键
     * 
     * @param string $key 缓存键
     * @return bool 成功返回 true
     */
    public function doDelete($key)
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * 清空缓存
     * 
     * @return bool 成功返回 true
     */
    public function doClear()
    {
        $this->cache = [];

        return true;
    }

    /**
     * 检查缓存键是否存在
     * 
     * @param string $key 缓存键
     * @return bool 存在返回 true，不存在返回 false
     */
    public function doHas($key)
    {
        return array_key_exists($key, $this->cache);
    }
}
