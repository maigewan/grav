<?php

/**
erelcms
 */

// 一些标准的常量定义
define('GRAV', true); // 定义 erelcms 标识
define('GRAV_VERSION', '1.7.48'); // 定义 erelcms 版本号
define('GRAV_SCHEMA', '1.7.0_2020-11-20_1'); // 定义 erelcms 数据库架构版本
define('GRAV_TESTING', false); // 测试模式开关

// PHP 的最低版本要求
if (!defined('GRAV_PHP_MIN')) {
    define('GRAV_PHP_MIN', '7.3.6'); // 定义最低 PHP 版本
}

// 目录分隔符
if (!defined('DS')) {
    define('DS', '/'); // 定义统一的目录分隔符
}

// erelcms 根目录的绝对路径。这是 erelcms 的安装目录。
if (!defined('GRAV_ROOT')) {
    $path = rtrim(str_replace(DIRECTORY_SEPARATOR, DS, getenv('GRAV_ROOT') ?: getcwd()), DS);
    define('GRAV_ROOT', $path ?: DS);
}

// erelcms 网站根目录的绝对路径。这是网站所在的路径。
if (!defined('GRAV_WEBROOT')) {
    $path = rtrim(getenv('GRAV_WEBROOT') ?: GRAV_ROOT, DS);
    define('GRAV_WEBROOT', $path ?: DS);
}

// 用户文件夹的相对路径。该路径需要位于 GRAV_WEBROOT 下。
if (!defined('GRAV_USER_PATH')) {
    $path = rtrim(getenv('GRAV_USER_PATH') ?: 'user', DS);
    define('GRAV_USER_PATH', $path);
}

// 系统文件夹的绝对或相对路径。默认为 GRAV_ROOT/system。
if (!defined('GRAV_SYSTEM_PATH')) {
    $path = rtrim(getenv('GRAV_SYSTEM_PATH') ?: 'system', DS);
    define('GRAV_SYSTEM_PATH', $path);
}

// 缓存文件夹的绝对或相对路径。默认为 GRAV_ROOT/cache
if (!defined('GRAV_CACHE_PATH')) {
    $path = rtrim(getenv('GRAV_CACHE_PATH') ?: 'cache', DS);
    define('GRAV_CACHE_PATH', $path);
}

// 日志文件夹的绝对或相对路径。默认为 GRAV_ROOT/logs
if (!defined('GRAV_LOG_PATH')) {
    $path = rtrim(getenv('GRAV_LOG_PATH') ?: 'logs', DS);
    define('GRAV_LOG_PATH', $path);
}

// 临时文件夹的绝对或相对路径。默认为 GRAV_ROOT/tmp
if (!defined('GRAV_TMP_PATH')) {
    $path = rtrim(getenv('GRAV_TMP_PATH') ?: 'tmp', DS);
    define('GRAV_TMP_PATH', $path);
}

// 备份文件夹的绝对或相对路径。默认为 GRAV_ROOT/backup
if (!defined('GRAV_BACKUP_PATH')) {
    $path = rtrim(getenv('GRAV_BACKUP_PATH') ?: 'backup', DS);
    define('GRAV_BACKUP_PATH', $path);
}

unset($path); // 释放变量

// 内部定义：不要使用！
define('USER_DIR', GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/'); // 用户目录
define('CACHE_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_CACHE_PATH) ? GRAV_ROOT . '/' : '') . GRAV_CACHE_PATH . '/'); // 缓存目录

// 已废弃定义：不要使用！
define('CACHE_PATH', GRAV_CACHE_PATH . DS); // 缓存路径
define('USER_PATH', GRAV_USER_PATH . DS); // 用户路径
define('ROOT_DIR', GRAV_ROOT . DS); // 根目录
define('ASSETS_DIR', GRAV_WEBROOT . '/assets/'); // 资源目录
define('IMAGES_DIR', GRAV_WEBROOT . '/images/'); // 图像目录
define('ACCOUNTS_DIR', USER_DIR . 'accounts/'); // 账户目录
define('PAGES_DIR', USER_DIR . 'pages/'); // 页面目录
define('DATA_DIR', USER_DIR . 'data/'); // 数据目录
define('PLUGINS_DIR', USER_DIR . 'plugins/'); // 插件目录
define('THEMES_DIR', USER_DIR . 'themes/'); // 主题目录
define('SYSTEM_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_SYSTEM_PATH) ? GRAV_ROOT . '/' : '') . GRAV_SYSTEM_PATH . '/'); // 系统目录
define('LIB_DIR', SYSTEM_DIR . 'src/'); // 库目录
define('VENDOR_DIR', GRAV_ROOT . '/vendor/'); // 第三方依赖目录
define('LOG_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_LOG_PATH) ? GRAV_ROOT . '/' : '') . GRAV_LOG_PATH . '/'); // 日志目录
// 已废弃定义结束

// 一些扩展名定义
define('CONTENT_EXT', '.md'); // 内容扩展名
define('TEMPLATE_EXT', '.html.twig'); // 模板扩展名
define('TWIG_EXT', '.twig'); // Twig 扩展名
define('PLUGIN_EXT', '.php'); // 插件扩展名
define('YAML_EXT', '.yaml'); // YAML 扩展名

// 内容类型
define('RAW_CONTENT', 1); // 原始内容
define('TWIG_CONTENT', 2); // Twig 内容
define('TWIG_CONTENT_LIST', 3); // Twig 内容列表
define('TWIG_TEMPLATES', 4); // Twig 模板

// 过滤器
define('GRAV_SANITIZE_STRING', 5001); // 字符串清理过滤器
