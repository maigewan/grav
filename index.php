<?php

/**
 *版权所有：https://www.erelcms.com
 * 作者：erelseo
 */

namespace Grav;

// 定义 GRAV_REQUEST_TIME 常量，表示请求的开始时间（精确到微秒）。
\define('GRAV_REQUEST_TIME', microtime(true));

// 定义  常量，表示支持的最低 PHP 版本。
\define('GRAV_PHP_MIN', '7.3.6');

// 检查是否运行于 PHP 内置的 CLI 服务器中。
if (PHP_SAPI === 'cli-server') {
    // 检测是否运行在 Symfony 的开发服务器中。
    $symfony_server = stripos(getenv('_'), 'symfony') !== false 
        || stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'symfony') !== false 
        || stripos($_ENV['SERVER_SOFTWARE'] ?? '', 'symfony') !== false;

    // 如果未设置 PHP 内置路由器且不是 Symfony 开发服务器，则停止运行并显示错误信息。
    if (!isset($_SERVER['PHP_CLI_ROUTER']) && !$symfony_server) {
        die("PHP 内置服务器需要一个路由器来运行 erel，请使用以下命令启动：<pre>php -S {$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']} system/router.php</pre>");
    }
}

// 确保 vendor 目录中的依赖库已安装。
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    // 如果 autoload 文件不存在，提示用户安装依赖库。
    die('请查看官网：<i>https://www.erelcms.com</i>');
}

// 注册自动加载器。
$loader = require $autoload;

// 设置默认时区。如果 php.ini 中未设置时区，使用系统默认时区。
date_default_timezone_set(@date_default_timezone_get());

// 设置内部字符编码为 UTF-8。
@ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

// 获取 erel 实例。
$grav = Grav::instance(array('loader' => $loader));

// 处理页面请求。
try {
    $grav->process();
} catch (\Error|\Exception $e) {
    // 捕获致命错误或异常，触发 `onFatalException` 事件并传递异常信息。
    $grav->fireEvent('onFatalException', new Event(array('exception' => $e)));
    // 重新抛出异常。
    throw $e;
}
