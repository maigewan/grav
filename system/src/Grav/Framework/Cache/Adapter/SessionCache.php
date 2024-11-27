<?php
namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;

/**
 * SessionCache 类
 * 
 * 此类为 PSR-16 兼容的 "简单缓存" 提供基于 PHP 会话的实现。
 * 使用会话存储缓存数据，并支持 TTL（生存时间）管理。
 * 
 * 特性：
 * - 数据存储在 PHP 会话中（`$_SESSION`）。
 * - 支持缓存数据的自动过期。
 * - 命名空间隔离，避免会话中数据冲突。
 * 
 * @package Grav\Framework\Cache
 */
class SessionCache extends AbstractCache
{
    public const VALUE = 0;       // 数据值的键
    public const LIFETIME = 1;   // 数据过期时间的键

    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $miss 如果键不存在时返回的默认值
     * @return mixed 返回缓存值或默认值
     */
    public function doGet($key, $miss)
    {
        $stored = $this->doGetStored($key);

        return $stored ? $stored[self::VALUE] : $miss;
    }

    /**
     * 设置缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 生存时间（TTL），单位为秒
     * @return bool 成功返回 true
     */
    public function doSet($key, $value, $ttl)
    {
        $stored = [self::VALUE => $value];
        if (null !== $ttl) {
            $stored[self::LIFETIME] = time() + $ttl;
        }

        $_SESSION[$this->getNamespace()][$key] = $stored;

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
        unset($_SESSION[$this->getNamespace()][$key]);

        return true;
    }

    /**
     * 清空所有缓存
     * 
     * @return bool 成功返回 true
     */
    public function doClear()
    {
        unset($_SESSION[$this->getNamespace()]);

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
        return $this->doGetStored($key) !== null;
    }

    /**
     * 获取当前缓存的命名空间
     * 
     * @return string 返回完整的命名空间
     */
    public function getNamespace()
    {
        return 'cache-' . parent::getNamespace();
    }

    /**
     * 获取存储的缓存数据
     * 
     * @param string $key 缓存键
     * @return mixed|null 返回存储的数据或 null
     */
    protected function doGetStored($key)
    {
        $stored = $_SESSION[$this->getNamespace()][$key] ?? null;

        // 检查是否设置了过期时间，并判断是否已经过期
        if (isset($stored[self::LIFETIME]) && $stored[self::LIFETIME] < time()) {
            unset($_SESSION[$this->getNamespace()][$key]);
            $stored = null;
        }

        return $stored ?: null;
    }
}
