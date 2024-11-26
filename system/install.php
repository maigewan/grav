<?php
if (!defined('GRAV_ROOT')) { // 如果没有定义 GRAV_ROOT 常量
    die(); // 停止执行。
}

require_once __DIR__ . '/src/Grav/Installer/Install.php'; // 引入安装程序的主文件

return Grav\Installer\Install::instance(); // 返回 Install 类的单例实例
