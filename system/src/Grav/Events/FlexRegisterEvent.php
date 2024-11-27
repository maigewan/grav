<?php
namespace Grav\Events;

use Grav\Framework\Flex\Flex;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * FlexRegisterEvent 类
 * 
 * Flex 注册事件。
 * 
 * 功能：
 * - 此事件在第一次调用 `$grav['flex']` 时触发。
 * - 用于注册启用的目录（Directories）到 Flex 框架。
 * 
 * 特性：
 * - 提供 `Flex` 实例作为事件的属性。
 * - 插件开发者可以通过监听此事件自定义 Flex 框架的注册行为。
 * 
 * 使用场景：
 * - 在应用程序或插件中动态注册 Flex Directories。
 * - 在 Flex 框架初始化时执行自定义逻辑。
 * 
 * @property Flex $flex Flex 实例
 */
class FlexRegisterEvent extends Event
{
    /** @var Flex Flex 实例 */
    public $flex;

    /**
     * 构造函数
     * 
     * @param Flex $flex Flex 实例
     */
    public function __construct(Flex $flex)
    {
        $this->flex = $flex;
    }

    /**
     * 调试信息方法
     * 
     * @return array 返回事件的属性数组
     */
    public function __debugInfo(): array
    {
        return (array)$this;
    }
}
