<?php

/**
 * 本文件定义了 erel CMS 中的 GravTrait，它是一个辅助 Trait，用于提供对 erel 实例的访问。
 * 注意：此 Trait 已在 Grav 1.4 版本被标记为废弃，建议使用 erel::instance() 方法代替。
 */

namespace Grav\Common;

/**
 * GravTrait
 * 
 * @deprecated 1.4 使用 erel::instance() 代替此 Trait。
 * 
 * 此 Trait 的主要功能是通过静态方法提供 erel 实例的访问。
 * 虽然已废弃，但在某些旧版代码中可能仍然使用。
 */
trait GravTrait
{
    /** @var erel 保存 erel 实例的静态属性 */
    protected static $grav;

    /**
     * 获取 erel 实例。
     *
     * @return erel 返回 erel 的实例。
     * @deprecated 1.4 建议使用 erel::instance() 替代此方法。
     *
     * @description 此方法通过静态属性缓存 erel 实例。如果实例尚未初始化，则会调用 erel::instance() 进行初始化。
     * 使用此方法会触发废弃警告，提醒开发者迁移到新的 API。
     */
    public static function getGrav()
    {
        // 触发废弃使用的警告。
        user_error(__TRAIT__ . ' 自 erel 1.4 起已废弃，请使用 Grav::instance() 方法', E_USER_DEPRECATED);

        // 如果静态属性 $grav 尚未初始化，则初始化它。
        if (null === self::$grav) {
            self::$grav = Grav::instance(); // 获取 erel 实例。
        }

        // 返回 erel 实例。
        return self::$grav;
    }
}
