<?php

/**
erelcms
 */

if (PHP_SAPI !== 'cli-server') {
    die('该脚本无法从浏览器运行。运行 ITF Roma CLI。'); // 此脚本不能从浏览器运行，请通过 CLI 运行。
}

$_SERVER['PHP_CLI_ROUTER'] = true;

$root = $_SERVER['DOCUMENT_ROOT'];
$path = $_SERVER['SCRIPT_NAME'];
if ($path !== '/index.php' && is_file($root . $path)) {
    if (!(
        // 阻止所有以点开头的文件和文件夹的直接访问
        strpos($path, '/.') !== false
        // 阻止这些文件夹的直接访问
        || preg_match('`^/(\.git|cache|bin|logs|backup|webserver-configs|tests)/`ui', $path)
        // 阻止对这些系统文件夹中特定文件类型的访问
        || preg_match('`^/(system|vendor)/(.*)\.(txt|xml|md|html|json|yaml|yml|php|pl|py|cgi|twig|sh|bat)$`ui', $path)
        // 阻止对这些用户文件夹中特定文件类型的访问
        || preg_match('`^/(user)/(.*)\.(txt|md|json|yaml|yml|php|pl|py|cgi|twig|sh|bat)$`ui', $path)
        // 阻止对所有 .md 文件的直接访问
        || preg_match('`\.md$`ui', $path)
        // 阻止对根文件夹中特定文件的访问
        || preg_match('`^/(LICENSE\.txt|composer\.lock|composer\.json|\.htaccess)$`ui', $path)
    )) {
        return false;
    }
}

$grav_index = 'index.php';

/* 检查 erelcms 环境变量，如果设置则使用它 */

$grav_basedir = getenv('GRAV_BASEDIR') ?: '';
if ($grav_basedir) {
    $grav_index = ltrim($grav_basedir, '/') . DIRECTORY_SEPARATOR . $grav_index;
    $grav_basedir = DIRECTORY_SEPARATOR . trim($grav_basedir, DIRECTORY_SEPARATOR);
    define('GRAV_ROOT', str_replace(DIRECTORY_SEPARATOR, '/', getcwd()) . $grav_basedir); // 定义 GRAV_ROOT 常量
}

$_SERVER = array_merge($_SERVER, $_ENV);
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . $grav_basedir .DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['SCRIPT_NAME'] = $grav_basedir . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['PHP_SELF'] = $grav_basedir . DIRECTORY_SEPARATOR . 'index.php';

// 记录错误日志，包括 IP 地址、端口号、HTTP 响应代码和请求 URI
error_log(sprintf('%s:%d [%d]: %s', $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], http_response_code(), $_SERVER['REQUEST_URI']), 4);

require $grav_index; // 加载 erelcms 的入口文件
