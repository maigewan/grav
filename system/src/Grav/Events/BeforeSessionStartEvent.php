<?php
namespace Grav\Events;

use Grav\Framework\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * BeforeSessionStartEvent 类
 * 
 * 此事件在调用 `$grav['session']->start()` 方法并且执行 `session_start()` 之前触发。
 * 
 * 功能：
 * - 提供一个在会话（Session）开始之前执行自定义逻辑的机会。
 * - 允许开发者访问会话实例（`SessionInterface`），以便进行相关操作。
 * 
 * 特性：
 * - 包含 `SessionInterface` 的实例作为事件的属性。
 * - 实现了 `__debugInfo` 方法，便于调试时查看事件的详细信息。
 * 
 * 使用场景：
 * - 插件开发者可以通过监听此事件来自定义会话启动前的行为，例如设置会话参数或执行其他初始化任务。
 * 
 * @property SessionInterface $session 会话实例
 */
class BeforeSessionStartEvent extends Event
{
    /** @var SessionInterface 会话实例 */
    public $session;

    /**
     * 构造函数
     * 
     * @param SessionInterface $session 会话实例
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
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
