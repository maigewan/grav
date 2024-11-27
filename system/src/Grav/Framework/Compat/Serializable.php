<?php

namespace Grav\Framework\Compat;

/**
 * 可序列化（Serializable）特性
 *
 * 此特性为 PHP 7.3 的 Serializable 接口提供向后兼容支持。
 * 
 * 注意事项：
 * 1. 在使用此特性的类中，必须添加 `implements \Serializable`。
 * 2. Serializable 接口允许对象以某种方式序列化或反序列化，例如用于缓存或持久化。
 * 3. PHP 7.4 之后官方建议使用 `__serialize()` 和 `__unserialize()` 方法，此特性实际上将其封装以兼容旧版本。
 *
 * 主要作用：
 * 1. 提供 `serialize` 方法，将当前对象的状态序列化为字符串。
 * 2. 提供 `unserialize` 方法，将序列化字符串还原为对象。
 * 3. 增加对序列化安全性的支持（如限制允许的反序列化类）。
 *
 * 使用方法示例：
 * ```php
 * class MyClass implements \Serializable {
 *     use Serializable;
 *     // 你的类实现代码...
 * }
 * ```
 *
 * @package Grav\Framework\Traits
 */
trait Serializable
{
    /**
     * 将对象序列化为字符串
     *
     * @return string 序列化后的字符串表示
     */
    final public function serialize(): string
    {
        // 调用对象的 __serialize() 方法并将返回结果序列化
        return serialize($this->__serialize());
    }

    /**
     * 从序列化字符串恢复对象状态
     *
     * @param string $serialized 序列化后的字符串
     * @return void
     */
    final public function unserialize($serialized): void
    {
        // 调用对象的 __unserialize() 方法并传入反序列化后的数据
        $this->__unserialize(unserialize($serialized, ['allowed_classes' => $this->getUnserializeAllowedClasses()]));
    }

    /**
     * 获取允许反序列化的类列表
     *
     * @return array|bool 如果返回 false，则禁止反序列化任何类。
     *                    如果返回数组，则仅允许反序列化数组中的类。
     *                    默认情况下返回 false，表示为了安全不允许任何类被反序列化。
     * 
     * 安全提示：
     * 反序列化操作可能会带来安全隐患（例如反序列化恶意对象导致代码注入）。
     * 可以通过覆盖此方法来限制允许反序列化的类以提升安全性。
     */
    protected function getUnserializeAllowedClasses()
    {
        return false; // 默认不允许反序列化任何类
    }
}
