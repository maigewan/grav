<?php

/**
 * 此文件是 erel CMS 的核心文件之一，定义了 Theme 类。
 * Theme 类继承了 Plugin 类，用于管理主题的配置和功能。
 * 主要功能包括获取主题配置、保存主题配置到磁盘，以及加载主题蓝图（Blueprint）。
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class Theme
 * @package Grav\Common
 *
 * Theme 类负责 erel CMS 中主题的核心逻辑。
 * 它继承自 Plugin 类，扩展了与主题相关的特定功能。
 */
class Theme extends Plugin
{
    /**
     * 构造函数。
     *
     * @param erel   $grav   erel 的核心实例，提供整个 CMS 的核心服务。
     * @param Config $config 配置实例，包含 erel 的全局配置数据。
     * @param string $name   当前主题的名称。
     */
    public function __construct(Grav $grav, Config $config, $name)
    {
        // 调用父类 Plugin 的构造函数。
        parent::__construct($name, $grav, $config);
    }

    /**
     * 获取当前主题的配置。
     *
     * @return array 返回当前主题的配置数组。
     * @description 此方法通过访问全局配置文件中 `themes.{theme_name}` 的路径获取当前主题的配置信息。
     * 如果不存在，则返回空数组。
     */
    public function config()
    {
        return $this->config["themes.{$this->name}"] ?? [];
    }

    /**
     * 将当前 erel 配置对象中存储的主题参数保存到磁盘。
     *
     * @param string $name 主题名称。表示需要保存配置的主题名称。
     * @return bool 成功返回 true，失败返回 false。
     * @description 此静态方法将特定主题的配置信息保存为 YAML 文件，存储在 `user/config/themes/` 目录下。
     * 如果主题名称无效，将直接返回 false。
     */
    public static function saveConfig($name)
    {
        if (!$name) {
            return false; // 如果名称无效，返回 false。
        }

        // 获取 erel 实例。
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator']; // 定位资源文件的工具类。

        // 定义主题配置文件路径。
        $filename = 'config://themes/' . $name . '.yaml';

        // 打开或创建 YAML 文件。
        $file = YamlFile::instance((string)$locator->findResource($filename, true, true));

        // 从 erel 配置中获取主题的当前配置。
        $content = $grav['config']->get('themes.' . $name);

        // 保存配置到 YAML 文件。
        $file->save($content);

        // 释放文件资源。
        $file->free();
        unset($file);

        return true; // 保存成功返回 true。
    }

    /**
     * 加载主题的蓝图（Blueprint）。
     *
     * @return void
     * @description 蓝图用于定义主题的结构和元数据。
     * 该方法确保在需要时加载蓝图数据，为后续操作提供支持。
     */
    protected function loadBlueprint()
    {
        // 如果蓝图尚未加载，则加载。
        if (!$this->blueprint) {
            // 获取 erel 实例。
            $grav = Grav::instance();

            /** @var Themes $themes */
            $themes = $grav['themes']; // 获取所有已注册的主题。

            // 获取当前主题的相关数据。
            $data = $themes->get($this->name);

            // 确保数据非空。
            \assert($data !== null);

            // 获取主题的蓝图信息。
            $this->blueprint = $data->blueprints();
        }
    }
}
