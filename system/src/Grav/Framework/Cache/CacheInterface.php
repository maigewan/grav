<?php
namespace Grav\Framework\Cache;

use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

/**
 * PSR-16 兼容的 "简单缓存" 接口
 * 
 * 介绍：
 * 该接口扩展了 PSR-16 定义的 `SimpleCacheInterface`，提供一组标准化的缓存操作方法。
 * 同时添加了一些更底层的 `do*` 方法以支持自定义的缓存实现。
 * 
 * 特性：
 * 1. 提供了一系列缓存操作方法，例如获取、设置、删除、清除缓存等。
 * 2. 支持多键值操作，如批量获取、批量设置和批量删除。
 * 3. 添加了 `do*` 系列方法，用于对缓存的底层逻辑提供更精细的控制。
 * 
 * 使用场景：
 * - 适用于需要兼容 PSR-16 标准的缓存系统。
 * - 方便开发者构建统一的缓存接口实现，同时允许覆盖底层逻辑。
 * 
 * @package Grav\Framework\Object\Storage
 */
interface CacheInterface extends SimpleCacheInterface
{
    /**
     * 从缓存中获取指定键的值
     *
     * @param string $key 缓存键
     * @param mixed $miss 如果键不存在，返回的默认值
     * @return mixed 返回键对应的值，如果不存在则返回 `$miss`
     */
    public function doGet($key, $miss);

    /**
     * 将指定键的值存入缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 要存储的值
     * @param int|null $ttl 可选的生存时间（单位：秒），如果为 null 则使用默认值
     * @return mixed 成功时返回 true，否则返回 false
     */
    public function doSet($key, $value, $ttl);

    /**
     * 删除指定键的缓存
     *
     * @param string $key 缓存键
     * @return mixed 成功时返回 true，否则返回 false
     */
    public function doDelete($key);

    /**
     * 清空所有缓存
     *
     * @return bool 成功时返回 true，否则返回 false
     */
    public function doClear();

    /**
     * 批量获取多个键对应的值
     *
     * @param string[] $keys 缓存键数组
     * @param mixed $miss 如果某个键不存在，返回的默认值
     * @return mixed 返回一个键值对数组，如果键不存在则使用 `$miss`
     */
    public function doGetMultiple($keys, $miss);

    /**
     * 批量设置多个键值对
     *
     * @param array<string, mixed> $values 要存储的键值对数组
     * @param int|null $ttl 可选的生存时间（单位：秒），如果为 null 则使用默认值
     * @return mixed 成功时返回 true，否则返回 false
     */
    public function doSetMultiple($values, $ttl);

    /**
     * 批量删除多个键
     *
     * @param string[] $keys 要删除的缓存键数组
     * @return mixed 成功时返回 true，否则返回 false
     */
    public function doDeleteMultiple($keys);

    /**
     * 检查指定键是否存在于缓存中
     *
     * @param string $key 缓存键
     * @return mixed 如果键存在返回 true，否则返回 false
     */
    public function doHas($key);
}
