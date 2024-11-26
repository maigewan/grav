<?php
namespace Grav\Common;

use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class Yaml
 * Yaml 类用于解析和生成 YAML 数据格式，方便在 Grav CMS 中进行数据存储和配置处理。
 * @package Grav\Common
 */
abstract class Yaml
{
    /** 
     * @var YamlFormatter|null 
     * 静态属性，用于存储 YamlFormatter 实例。
     */
    protected static $yaml;

    /**
     * 解析 YAML 数据为 PHP 数组。
     *
     * @param string $data YAML 格式的字符串
     * @return array 返回解析后的数组
     */
    public static function parse($data)
    {
        if (null === static::$yaml) {
            static::init(); // 如果尚未初始化，则调用 init() 方法。
        }

        return static::$yaml->decode($data); // 使用 YamlFormatter 解码数据。
    }

    /**
     * 将 PHP 数组转换为 YAML 格式的字符串。
     *
     * @param array $data 要转换的 PHP 数组
     * @param int|null $inline 指定内联层级（可选）
     * @param int|null $indent 指定缩进空格数（可选）
     * @return string 返回生成的 YAML 字符串
     */
    public static function dump($data, $inline = null, $indent = null)
    {
        if (null === static::$yaml) {
            static::init(); // 如果尚未初始化，则调用 init() 方法。
        }

        return static::$yaml->encode($data, $inline, $indent); // 使用 YamlFormatter 编码数据。
    }

    /**
     * 初始化 YamlFormatter。
     *
     * @return void
     * 该方法会根据配置初始化 YamlFormatter 实例。
     */
    protected static function init()
    {
        $config = [
            'inline' => 5,   // 指定内联解析的层级。
            'indent' => 2,   // 每层缩进的空格数。
            'native' => true, // 启用原生解析器。
            'compat' => true  // 启用兼容模式。
        ];

        static::$yaml = new YamlFormatter($config); // 创建一个新的 YamlFormatter 实例。
    }
}
