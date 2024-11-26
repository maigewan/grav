<?php

/**
 * 本文件定义了 erel CMS 中的 Browser 类，主要用于解析和检测用户的浏览器和平台信息。
 * 它内部依赖于第三方库 PhpUserAgent (https://github.com/donatj/PhpUserAgent)，
 * 提供了浏览器类型、平台、版本检测以及其他功能（如判断是否为人类请求）。
 */

namespace Grav\Common;

use InvalidArgumentException;
use function donatj\UserAgent\parse_user_agent;

/**
 * Browser 类
 * 
 * 通过解析用户代理（User Agent）字符串，提供与客户端浏览器和平台相关的信息。
 */
class Browser
{
    /** @var string[] 用户代理解析结果的存储 */
    protected $useragent = [];

    /**
     * 构造函数
     *
     * @description 在实例化时解析用户代理字符串，初始化解析结果。
     * 如果解析失败，使用默认的未知用户代理字符串。
     */
    public function __construct()
    {
        try {
            $this->useragent = parse_user_agent(); // 解析当前用户代理。
        } catch (InvalidArgumentException $e) {
            // 如果解析失败，设置默认值。
            $this->useragent = parse_user_agent("Mozilla/5.0 (compatible; Unknown;)");
        }
    }

    /**
     * 获取当前浏览器的标识符
     *
     * 支持检测的浏览器类型包括（不区分大小写）：
     * - Android Browser
     * - BlackBerry Browser
     * - Camino
     * - Kindle / Silk
     * - Firefox / Iceweasel
     * - Safari
     * - Internet Explorer
     * - IEMobile
     * - Chrome
     * - Opera
     * - Midori
     * - Vivaldi
     * - TizenBrowser
     * - Lynx
     * - Wget
     * - Curl
     *
     * @return string 返回小写的浏览器名称。
     */
    public function getBrowser()
    {
        return strtolower($this->useragent['browser']);
    }

    /**
     * 获取当前平台的标识符
     *
     * 支持检测的平台包括：
     * - 桌面（Desktop）
     *   - Windows
     *   - Linux
     *   - Macintosh
     *   - Chrome OS
     * - 移动设备（Mobile）
     *   - Android
     *   - iPhone
     *   - iPad / iPod Touch
     *   - Windows Phone OS
     *   - Kindle / Kindle Fire
     *   - BlackBerry / Playbook
     *   - Tizen
     * - 游戏控制台（Console）
     *   - Nintendo 3DS / New Nintendo 3DS
     *   - Nintendo Wii / WiiU
     *   - PlayStation 3 / 4 / Vita
     *   - Xbox 360 / One
     *
     * @return string 返回小写的平台名称。
     */
    public function getPlatform()
    {
        return strtolower($this->useragent['platform']);
    }

    /**
     * 获取完整的浏览器版本号
     *
     * @return string 返回浏览器完整的版本号。
     */
    public function getLongVersion()
    {
        return $this->useragent['version'];
    }

    /**
     * 获取浏览器主版本号
     *
     * @return int 返回浏览器的主版本号。
     */
    public function getVersion()
    {
        $version = explode('.', $this->getLongVersion()); // 按点分隔版本号。

        return (int)$version[0]; // 返回主版本号（整数）。
    }

    /**
     * 判断请求是否来自人类
     *
     * @return bool 如果请求来自人类返回 true，否则返回 false。
     * @description 通过检测用户代理中是否包含 "bot" 或 "crawl" 等关键字来判断请求是否来自爬虫。
     */
    public function isHuman()
    {
        $browser = $this->getBrowser();
        if (empty($browser)) {
            return false; // 如果浏览器信息为空，则不是人类请求。
        }

        if (preg_match('~(bot|crawl)~i', $browser)) {
            return false; // 如果用户代理包含 "bot" 或 "crawl"，返回 false。
        }

        return true; // 其他情况默认为人类请求。
    }

    /**
     * 判断浏览器是否启用了 “Do Not Track”（请勿追踪）
     *
     * @see https://www.w3.org/TR/tracking-dnt/
     * @return bool 如果未设置 “Do Not Track” 或设置为允许追踪，返回 true。
     */
    public function isTrackable(): bool
    {
        return !(isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1');
    }
}
