<?php
namespace Grav\Framework\Cache;

use DateInterval;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * 抽象缓存类，支持 PSR-16 的 "简单缓存" 实现
 * 
 * 此类是一个抽象的缓存实现类，提供了 PSR-16 兼容的基础实现。
 * 它使用了 `CacheTrait` 提供缓存操作的核心功能，并实现了 `CacheInterface` 接口。
 * 
 * 特性：
 * - 使用 `CacheTrait` 实现缓存功能。
 * - 通过构造函数初始化缓存命名空间和默认缓存生存时间。
 * 
 * 注意：
 * - 此类是抽象类，必须在子类中实现具体的存储机制。
 * 
 * @package Grav\Framework\Cache
 */
abstract class AbstractCache implements CacheInterface
{
    use CacheTrait; // 使用 CacheTrait 提供核心缓存操作方法

    /**
     * 构造函数，用于初始化缓存
     * 
     * @param string $namespace 缓存命名空间，用于隔离不同缓存实例的数据，避免冲突
     * @param null|int|DateInterval $defaultLifetime 默认缓存生存时间（TTL），单位为秒
     *                                                如果传递 DateInterval，则将其转换为秒。
     *                                                如果为 null，则使用缓存系统的默认 TTL。
     * @throws InvalidArgumentException 如果传递的 $defaultLifetime 参数无效，则抛出异常
     * 
     * 功能：
     * - 初始化命名空间和默认生存时间，确保缓存数据有唯一性和可控的生命周期。
     * 
     * 示例：
     * ```php
     * $cache = new MyCache('my_namespace', 3600); // 使用 'my_namespace'，默认生存时间为 3600 秒
     * ```
     */
    public function __construct($namespace = '', $defaultLifetime = null)
    {
        // 初始化缓存的命名空间和默认生存时间
        $this->init($namespace, $defaultLifetime);
    }
}
