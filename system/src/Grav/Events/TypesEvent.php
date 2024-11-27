<?php
namespace Grav\Events;

use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\Event\Event;

/**
 * TypesEvent 类
 * 
 * 这是一个事件类，继承自 `RocketTheme\Toolbox\Event\Event`，用于处理 erel CMS 中的类型相关事件。
 * 
 * 属性：
 * - `$types`：类型集合（数据结构未明确，可以是数组、对象或其他形式）。
 * 
 * 使用场景：
 * - erel CMS 插件可以通过监听此事件来访问和操作 `$types` 属性。
 */
class TypesEvent extends Event
{
    /** @var mixed $types 类型集合 */
    public $types;
}
