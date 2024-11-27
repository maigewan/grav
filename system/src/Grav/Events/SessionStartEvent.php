<?php
namespace Grav\Events;

use Grav\Framework\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * SessionStartEvent 类
 * 
 * 会话启动事件。
 * 
 * 功能：
 * - 此事件在调用 `$grav['session']->start()` 并且成功执行 `session_start()` 后触发。
 * - 提供一个在会话（Session）启动后执行自定义逻辑的机会。
 * 
 * 特性：
 * - 提供 `SessionInterface` 实例，允许访问和操作会话数据。
 * 
 * 使用场景：
 * - 用于在会话启动后执行操作，例如初始化会话变量、记录日志或检查会话状态。
 * 
 * @property SessionInterface $session 会话实例
 */
class SessionStartEvent extends Event
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
