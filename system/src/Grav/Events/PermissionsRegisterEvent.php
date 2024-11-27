<?php
namespace Grav\Events;

use Grav\Framework\Acl\Permissions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * PermissionsRegisterEvent 类
 * 
 * 权限注册事件。
 * 
 * 功能：
 * - 此事件在第一次调用 `$grav['permissions']` 时触发。
 * - 用于注册插件中使用的新权限类型。
 * 
 * 特性：
 * - 提供 `Permissions` 实例，允许插件或应用程序向权限系统添加自定义权限。
 * - 插件开发者可通过监听此事件动态扩展权限系统。
 * 
 * 使用场景：
 * - 向 erel CMS 中的权限管理系统添加自定义权限。
 * - 在插件中挂钩此事件以定义特定功能的权限控制。
 * 
 * @property Permissions $permissions 权限实例
 */
class PermissionsRegisterEvent extends Event
{
    /** @var Permissions 权限实例 */
    public $permissions;

    /**
     * 构造函数
     * 
     * @param Permissions $permissions 权限实例
     */
    public function __construct(Permissions $permissions)
    {
        $this->permissions = $permissions;
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
