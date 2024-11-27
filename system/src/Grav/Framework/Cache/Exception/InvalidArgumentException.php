<?php
namespace Grav\Framework\Cache\Exception;

use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

/**
 * InvalidArgumentException 类
 * 
 * 此类用于 PSR-16 兼容的 "简单缓存" 实现中表示无效参数的异常。
 * 它继承了 PHP 的基础 `InvalidArgumentException` 类，并实现了 PSR-16 标准定义的 `SimpleCacheInvalidArgumentException` 接口。
 * 
 * 主要功能：
 * - 标记由于无效参数引发的异常，例如：
 *   - 缓存键名不符合规范。
 *   - 缓存值类型不支持。
 *   - 其他与参数相关的错误。
 * 
 * 特性：
 * - 通过实现 PSR-16 接口，提供了与其他 PSR-16 缓存组件一致的异常处理方式。
 * - 扩展了标准 `InvalidArgumentException` 类的功能，便于开发者识别缓存参数错误。
 * 
 * 使用场景：
 * - 在缓存系统中，当方法接收到无效的参数时，抛出此异常。
 * 
 * 示例：
 * ```php
 * throw new InvalidArgumentException('无效的缓存键名');
 * ```
 * 
 * @package Grav\Framework\Cache\Exception
 */
class InvalidArgumentException extends \InvalidArgumentException implements SimpleCacheInvalidArgumentException
{
}
