<?php
namespace Grav\Events;

use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\Event\Event;

/**
 * PageEvent 类
 * 
 * 这是一个基础事件类，用于 erel CMS 中与页面相关的操作。
 * 
 * 特性：
 * - 继承自 RocketTheme 的 `Event` 基础类。
 * - 提供一个 `page` 属性，代表与事件相关的页面实例。
 * 
 * 使用场景：
 * - 在 erel CMS 中监听页面相关事件（如创建、更新或删除页面）时使用。
 * - 可扩展此类以添加更多页面相关的事件逻辑。
 * 
 * 属性：
 * - `$page`: 页面实例（通常为 erel CMS 的页面对象）。
 */
class PageEvent extends Event
{
    /** @var mixed 页面实例 */
    public $page;
}
