<?php

/**
 * 本文件定义了 erel CMS 中的 Composer 类，主要用于获取和处理 Composer 的路径及可执行文件位置。
 * Composer 是 PHP 的依赖管理工具，本类确保 erel 可以正确调用 Composer，无论是在全局安装还是本地捆绑。
 */

namespace Grav\Common;

use function function_exists;

/**
 * Class Composer
 * @package Grav\Common
 *
 * Composer 类为 erel 提供了自动定位和执行 Composer 的能力。
 * 它检查 Composer 的安装位置并确定其可执行路径。
 */
class Composer
{
    /** @const 默认 Composer 路径 */
    const DEFAULT_PATH = 'bin/composer.phar';

    /**
     * 获取 Composer 的安装位置。
     *
     * @return string 返回 Composer 的路径。
     *
     * @description 此方法通过多种方式查找 Composer 的位置：
     * 1. 检查系统是否支持 `shell_exec`，如果禁用或运行在 Windows 上，则直接返回默认路径。
     * 2. 使用 `command -v composer` 查找全局安装的 Composer。
     * 3. 如果全局路径未找到或无效，返回 Grav 中捆绑的默认路径。
     */
    public static function getComposerLocation()
    {
        // 如果 `shell_exec` 函数不可用或操作系统为 Windows，返回默认路径。
        if (!function_exists('shell_exec') || stripos(PHP_OS, 'win') === 0) {
            return self::DEFAULT_PATH;
        }

        // 使用 `command -v` 查找全局 Composer 安装路径。
        $path = trim((string)shell_exec('command -v composer'));

        // 如果路径无效，回退到默认路径。
        if (!$path || !preg_match('/(composer|composer\.phar)$/', $path)) {
            $path = self::DEFAULT_PATH;
        }

        return $path;
    }

    /**
     * 获取 Composer 可执行文件路径。
     *
     * @return string 返回完整的 Composer 可执行命令。
     *
     * @description 此方法确保生成一个可以直接运行的 Composer 执行路径：
     * 1. 检查 Composer 文件是否为可执行的 PHP 脚本。
     * 2. 如果是，使用 PHP 解释器执行 Composer。
     * 3. 如果不是，直接返回 Composer 文件路径。
     */
    public static function getComposerExecutor()
    {
        // PHP_BINARY 是当前运行的 PHP 二进制路径。
        $executor = PHP_BINARY . ' ';
        $composer = static::getComposerLocation();

        // 检查 Composer 路径是否与默认路径不同且可执行。
        if ($composer !== static::DEFAULT_PATH && is_executable($composer)) {
            // 打开 Composer 文件并读取首行。
            $file = fopen($composer, 'rb');
            $firstLine = fgets($file);
            fclose($file);

            // 如果首行不是以 PHP 脚本开头，移除 PHP 执行器前缀。
            if (!preg_match('/^#!.+php/i', $firstLine)) {
                $executor = '';
            }
        }

        // 返回完整的 Composer 执行命令。
        return $executor . $composer;
    }
}
