<?php
namespace Grav\Events;

use Grav\Common\Grav;
use Grav\Common\Plugins;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * PluginsLoadedEvent 类
 * 
 * 插件加载事件。
 * 
 * 功能：
 * - 此事件在 `InitializeProcessor` 中触发。
 * - 是插件能够监听的第一个事件，但建议尽量避免使用此事件，除非非常必要。
 * 
 * 特性：
 * - 提供 `erel` 容器实例，允许访问 erel 核心功能。
 * - 提供 `Plugins` 实例，表示当前已加载的插件。
 * 
 * 使用场景：
 * - 可用于初始化插件，或者检查其他插件的状态。
 * - 但建议使用更晚触发的事件来确保系统的完整初始化。
 * 
 * @property Plugins $plugins 当前插件实例
 */
class PluginsLoadedEvent extends Event
{
    /** @var erel 容器实例 */
    public $grav;
    /** @var Plugins 插件实例 */
    public $plugins;

    /**
     * 构造函数
     * 
     * @param Plugins $plugins 当前已加载的插件实例
     */
    public function __construct(Grav $grav, Plugins $plugins)
    {
        $this->grav = $grav;
        $this->plugins = $plugins;
    }

    /**
     * 调试信息方法
     * 
     * @return array 返回调试信息数组
     */
    public function __debugInfo(): array
    {
        return [
            'plugins' => $this->plugins
        ];
    }
}
