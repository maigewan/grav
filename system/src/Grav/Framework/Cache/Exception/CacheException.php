<?php
namespace Grav\Framework\Cache\Exception;

use Exception;
use Psr\SimpleCache\CacheException as SimpleCacheException;

/**
 * CacheException 类
 * 
 * 此类用于 PSR-16 兼容的 "简单缓存" 实现中表示缓存异常。
 * 它实现了 PSR-16 定义的 `SimpleCacheException` 接口，并继承了 PHP 的基础异常类 `Exception`。
 * 
 * 主要功能：
 * - 用于标记缓存系统中的异常情况。
 * - 提供统一的异常接口，便于在 PSR-16 兼容的缓存实现中处理异常。
 * 
 * 使用场景：
 * - 当缓存操作（如读取、写入或删除）发生错误时，抛出此异常。
 * - 例如：无效的键值、存储引擎失败、权限问题等。
 * 
 * 示例：
 * ```php
 * throw new CacheException('无法访问缓存存储');
 * ```
 * 
 * @package Grav\Framework\Cache\Exception
 */
class CacheException extends Exception implements SimpleCacheException
{
}
