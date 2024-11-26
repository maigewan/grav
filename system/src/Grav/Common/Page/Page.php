<?php
namespace Grav\Common\Page;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Traits\PageFormTrait;
use Grav\Common\Twig\Twig;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Framework\Flex\Flex;
use InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use RuntimeException;
use SplFileInfo;
use function dirname;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function strlen;

define('PAGE_ORDER_PREFIX_REGEX', '/^[0-9]+\./u');

/**
 * Page 类
 * @package Grav\Common\Page
 *
 * Page 类代表 Grav CMS 中的一个页面。它处理页面的各种属性和行为，如内容处理、路由、元数据、缓存等。
 * 该类实现了 PageInterface 接口，并使用了 PageFormTrait 和 MediaTrait 两个 trait，分别用于处理表单和媒体相关的功能。
 */
class Page implements PageInterface
{
    use PageFormTrait;
    use MediaTrait;

    /** @var string|null 文件名。如果页面是文件夹，则留空 */
    protected $name;
    /** @var bool 页面是否已初始化 */
    protected $initialized = false;
    /** @var string 页面所在的文件夹 */
    protected $folder;
    /** @var string 页面文件的路径 */
    protected $path;
    /** @var string 文件扩展名 */
    protected $extension;
    /** @var string URL 扩展名 */
    protected $url_extension;
    /** @var string 页面唯一标识符 */
    protected $id;
    /** @var string 页面父级路径 */
    protected $parent;
    /** @var string 页面模板 */
    protected $template;
    /** @var int 页面过期时间 */
    protected $expires;
    /** @var string 缓存控制头 */
    protected $cache_control;
    /** @var bool 页面在导航中是否可见 */
    protected $visible;
    /** @var bool 页面是否已发布 */
    protected $published;
    /** @var int 发布日期的时间戳 */
    protected $publish_date;
    /** @var int|null 取消发布日期的时间戳 */
    protected $unpublish_date;
    /** @var string 页面 slug，用于 URL 路由 */
    protected $slug;
    /** @var string|null 页面路由 */
    protected $route;
    /** @var string|null 原始路由 */
    protected $raw_route;
    /** @var string 页面 URL */
    protected $url;
    /** @var array 页面路由别名 */
    protected $routes;
    /** @var bool 页面是否可路由 */
    protected $routable;
    /** @var int 页面最后修改时间的时间戳 */
    protected $modified;
    /** @var string 页面重定向 URL */
    protected $redirect;
    /** @var string 页面外部 URL */
    protected $external_url;
    /** @var object|null 页面头信息 */
    protected $header;
    /** @var string 页面 Frontmatter */
    protected $frontmatter;
    /** @var string 页面语言 */
    protected $language;
    /** @var string|null 页面内容 */
    protected $content;
    /** @var array 页面内容的元数据 */
    protected $content_meta;
    /** @var string|null 页面摘要 */
    protected $summary;
    /** @var string 页面原始内容 */
    protected $raw_content;
    /** @var array|null 页面元数据 */
    protected $metadata;
    /** @var string 页面标题 */
    protected $title;
    /** @var int 页面最大数量 */
    protected $max_count;
    /** @var string 页面菜单名称 */
    protected $menu;
    /** @var int 页面日期的时间戳 */
    protected $date;
    /** @var string 页面日期格式 */
    protected $dateformat;
    /** @var array 页面分类法 */
    protected $taxonomy;
    /** @var string 页面排序依据 */
    protected $order_by;
    /** @var string 页面排序方向 */
    protected $order_dir;
    /** @var array|string|null 页面手动排序 */
    protected $order_manual;
    /** @var bool 页面是否使用模块化 Twig */
    protected $modular_twig;
    /** @var array 页面处理配置 */
    protected $process;
    /** @var int|null 页面摘要大小 */
    protected $summary_size;
    /** @var bool 是否使用 Markdown Extra */
    protected $markdown_extra;
    /** @var bool 是否启用 ETag */
    protected $etag;
    /** @var bool 是否启用 Last-Modified */
    protected $last_modified;
    /** @var string 首页路由 */
    protected $home_route;
    /** @var bool 是否隐藏首页路由 */
    protected $hide_home_route;
    /** @var bool 是否启用 SSL */
    protected $ssl;
    /** @var string 模板格式 */
    protected $template_format;
    /** @var bool 是否启用调试器 */
    protected $debugger;

    /** @var PageInterface|null 未修改的原始页面版本，用于复制和移动页面 */
    private $_original;
    /** @var string 操作类型 */
    private $_action;

    /**
     * Page 对象构造函数
     * 初始化页面的基本属性，如分类法和处理配置。
     */
    public function __construct()
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $this->taxonomy = [];
        $this->process = $config->get('system.pages.process');
        $this->published = true;
    }

    /**
     * 初始化页面实例变量，基于文件信息
     *
     * @param  SplFileInfo $file 页面对应的 .md 文件信息
     * @param  string|null $extension 文件扩展名
     * @return $this
     */
    public function init(SplFileInfo $file, $extension = null)
    {
        $config = Grav::instance()['config'];

        $this->initialized = true;

        // 处理文件扩展名
        if (empty($extension)) {
            $this->extension('.' . $file->getExtension());
        } else {
            $this->extension($extension);
        }

        // 从文件扩展名中提取页面语言
        $language = trim(Utils::basename($this->extension(), 'md'), '.') ?: null;
        $this->language($language);

        $this->hide_home_route = $config->get('system.home.hide_in_urls', false);
        $this->home_route = $this->adjustRouteCase($config->get('system.home.alias'));
        $this->filePath($file->getPathname());
        $this->modified($file->getMTime());
        $this->id($this->modified() . md5($this->filePath()));
        $this->routable(true);
        $this->header();
        $this->date();
        $this->metadata();
        $this->url();
        $this->visible();
        $this->modularTwig(strpos($this->slug(), '_') === 0);
        $this->setPublishState();
        $this->published();
        $this->urlExtension();

        return $this;
    }

    /**
     * 克隆 Page 对象时，重置部分属性以确保新对象的独立性
     */
    #[\ReturnTypeWillChange]
    public function __clone()
    {
        $this->initialized = false;
        $this->header = $this->header ? clone $this->header : null;
    }

    /**
     * 初始化页面，如果尚未初始化
     *
     * @return void
     */
    public function initialize(): void
    {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->route = null;
            $this->raw_route = null;
            $this->_forms = null;
        }
    }

    /**
     * 处理页面的 Frontmatter，支持 Twig 模板
     *
     * @return void
     */
    protected function processFrontmatter()
    {
        // 如果启用了 Twig 输出标签处理，进行相应处理
        $process_fields = (array)$this->header();
        if (Utils::contains(json_encode(array_values($process_fields)), '{{')) {
            $ignored_fields = [];
            foreach ((array)Grav::instance()['config']->get('system.pages.frontmatter.ignore_fields') as $field) {
                if (isset($process_fields[$field])) {
                    $ignored_fields[$field] = $process_fields[$field];
                    unset($process_fields[$field]);
                }
            }
            // 使用 Twig 处理前置内容
            $text_header = Grav::instance()['twig']->processString(json_encode($process_fields, JSON_UNESCAPED_UNICODE), ['page' => $this]);
            $this->header((object)(json_decode($text_header, true) + $ignored_fields));
        }
    }

    /**
     * 返回页面的翻译语言路由数组
     *
     * @param bool $onlyPublished 仅返回已发布的翻译
     * @return array 翻译语言的路由
     */
    public function translatedLanguages($onlyPublished = false)
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $defaultCode = $language->getDefault();

        $name = substr($this->name, 0, -strlen($this->extension()));
        $translatedLanguages = [];

        foreach ($languages as $languageCode) {
            $languageExtension = ".{$languageCode}.md";
            $path = $this->path . DS . $this->folder . DS . $name . $languageExtension;
            $exists = file_exists($path);

            // 默认语言可能不需要语言文件后缀
            if (!$exists && $languageCode === $defaultCode) {
                $languageExtension = '.md';
                $path = $this->path . DS . $this->folder . DS . $name . $languageExtension;
                $exists = file_exists($path);
            }

            if ($exists) {
                $aPage = new Page();
                $aPage->init(new SplFileInfo($path), $languageExtension);
                $aPage->route($this->route());
                $aPage->rawRoute($this->rawRoute());
                $route = $aPage->header()->routes['default'] ?? $aPage->rawRoute();
                if (!$route) {
                    $route = $aPage->route();
                }

                if ($onlyPublished && !$aPage->published()) {
                    continue;
                }

                $translatedLanguages[$languageCode] = $route;
            }
        }

        return $translatedLanguages;
    }

    /**
     * 返回未翻译的语言数组
     *
     * @param bool $includeUnpublished 也列出未发布的翻译
     * @return array 未翻译的语言数组
     */
    public function untranslatedLanguages($includeUnpublished = false)
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $translated = array_keys($this->translatedLanguages(!$includeUnpublished));

        return array_values(array_diff($languages, $translated));
    }

    /**
     * 获取或设置原始的页面内容
     *
     * @param  string|null $var 原始内容字符串
     * @return string      原始内容字符串
     */
    public function raw($var = null)
    {
        $file = $this->file();

        if ($var) {
            // 首先更新文件对象
            if ($file) {
                $file->raw($var);
            }

            // 重置 header 和 content
            $this->modified = time();
            $this->id($this->modified() . md5($this->filePath()));
            $this->header = null;
            $this->content = null;
            $this->summary = null;
        }

        return $file ? $file->raw() : '';
    }

    /**
     * 获取或设置页面的 Frontmatter
     *
     * @param string|null $var Frontmatter 内容
     *
     * @return string Frontmatter 内容
     */
    public function frontmatter($var = null)
    {
        if ($var) {
            $this->frontmatter = (string)$var;

            // 同步更新文件对象
            $file = $this->file();
            if ($file) {
                $file->frontmatter((string)$var);
            }

            // 强制重新处理内容
            $this->id(time() . md5($this->filePath()));
        }
        if (!$this->frontmatter) {
            $this->header();
        }

        return $this->frontmatter;
    }

    /**
     * 获取或设置页面的 header（YAML 配置）
     *
     * @param  object|array|null $var YAML 对象或数组，代表文件的配置
     * @return \stdClass      当前的 YAML 配置对象
     */
    public function header($var = null)
    {
        if ($var) {
            $this->header = (object)$var;

            // 同步更新文件对象
            $file = $this->file();
            if ($file) {
                $file->header((array)$var);
            }

            // 强制重新处理内容
            $this->id(time() . md5($this->filePath()));
        }
        if (!$this->header) {
            $file = $this->file();
            if ($file) {
                try {
                    $this->raw_content = $file->markdown();
                    $this->frontmatter = $file->frontmatter();
                    $this->header = (object)$file->header();

                    if (!Utils::isAdminPlugin()) {
                        // 如果存在 frontmatter.yaml 文件，将其与页面 header 合并
                        // 注意：页面自己的 frontmatter 具有优先权，会覆盖任何默认值
                        $frontmatter_filename = $this->path . '/' . $this->folder . '/frontmatter.yaml';
                        if (file_exists($frontmatter_filename)) {
                            $frontmatter_file = CompiledYamlFile::instance($frontmatter_filename);
                            $frontmatter_data = $frontmatter_file->content();
                            $this->header = (object)array_replace_recursive(
                                $frontmatter_data,
                                (array)$this->header
                            );
                            $frontmatter_file->free();
                        }

                        // 如果启用了 Twig 处理 Frontmatter，则进行处理
                        if (Grav::instance()['config']->get('system.pages.frontmatter.process_twig') === true) {
                            $this->processFrontmatter();
                        }
                    }
                } catch (Exception $e) {
                    // 处理 Frontmatter 解析错误
                    $file->raw(Grav::instance()['language']->translate([
                        'GRAV.FRONTMATTER_ERROR_PAGE',
                        $this->slug(),
                        $file->filename(),
                        $e->getMessage(),
                        $file->raw()
                    ]));
                    $this->raw_content = $file->markdown();
                    $this->frontmatter = $file->frontmatter();
                    $this->header = (object)$file->header();
                }
                $var = true;
            }
        }

        if ($var) {
            // 从 header 中提取并设置各个属性
            if (isset($this->header->modified)) {
                $this->modified($this->header->modified);
            }
            if (isset($this->header->slug)) {
                $this->slug($this->header->slug);
            }
            if (isset($this->header->routes)) {
                $this->routes = (array)$this->header->routes;
            }
            if (isset($this->header->title)) {
                $this->title = trim($this->header->title);
            }
            if (isset($this->header->language)) {
                $this->language = trim($this->header->language);
            }
            if (isset($this->header->template)) {
                $this->template = trim($this->header->template);
            }
            if (isset($this->header->menu)) {
                $this->menu = trim($this->header->menu);
            }
            if (isset($this->header->routable)) {
                $this->routable = (bool)$this->header->routable;
            }
            if (isset($this->header->visible)) {
                $this->visible = (bool)$this->header->visible;
            }
            if (isset($this->header->redirect)) {
                $this->redirect = trim($this->header->redirect);
            }
            if (isset($this->header->external_url)) {
                $this->external_url = trim($this->header->external_url);
            }
            if (isset($this->header->order_dir)) {
                $this->order_dir = trim($this->header->order_dir);
            }
            if (isset($this->header->order_by)) {
                $this->order_by = trim($this->header->order_by);
            }
            if (isset($this->header->order_manual)) {
                $this->order_manual = (array)$this->header->order_manual;
            }
            if (isset($this->header->dateformat)) {
                $this->dateformat($this->header->dateformat);
            }
            if (isset($this->header->date)) {
                $this->date($this->header->date);
            }
            if (isset($this->header->markdown_extra)) {
                $this->markdown_extra = (bool)$this->header->markdown_extra;
            }
            if (isset($this->header->taxonomy)) {
                $this->taxonomy($this->header->taxonomy);
            }
            if (isset($this->header->max_count)) {
                $this->max_count = (int)$this->header->max_count;
            }
            if (isset($this->header->process)) {
                foreach ((array)$this->header->process as $process => $status) {
                    $this->process[$process] = (bool)$status;
                }
            }
            if (isset($this->header->published)) {
                $this->published = (bool)$this->header->published;
            }
            if (isset($this->header->publish_date)) {
                $this->publishDate($this->header->publish_date);
            }
            if (isset($this->header->unpublish_date)) {
                $this->unpublishDate($this->header->unpublish_date);
            }
            if (isset($this->header->expires)) {
                $this->expires = (int)$this->header->expires;
            }
            if (isset($this->header->cache_control)) {
                $this->cache_control = $this->header->cache_control;
            }
            if (isset($this->header->etag)) {
                $this->etag = (bool)$this->header->etag;
            }
            if (isset($this->header->last_modified)) {
                $this->last_modified = (bool)$this->header->last_modified;
            }
            if (isset($this->header->ssl)) {
                $this->ssl = (bool)$this->header->ssl;
            }
            if (isset($this->header->template_format)) {
                $this->template_format = $this->header->template_format;
            }
            if (isset($this->header->debugger)) {
                $this->debugger = (bool)$this->header->debugger;
            }
            if (isset($this->header->append_url_extension)) {
                $this->url_extension = $this->header->append_url_extension;
            }
        }

        /**
         * 获取或设置页面语言
         *
         * @param string|null $var 设置新的语言
         * @return mixed 当前页面的语言
         */
        public function language($var = null)
        {
            if ($var !== null) {
                $this->language = $var;
            }

            return $this->language;
        }

        /**
         * 直接修改 header 中的某个值
         *
         * @param string $key 头信息键
         * @param mixed $value 头信息值
         */
        public function modifyHeader($key, $value)
        {
            $this->header->{$key} = $value;
        }

        /**
         * 获取页面的 HTTP 响应代码
         *
         * @return int HTTP 响应代码，默认为 200
         */
        public function httpResponseCode()
        {
            return (int)($this->header()->http_response_code ?? 200);
        }

        /**
         * 获取页面的 HTTP 头信息数组
         *
         * @return array HTTP 头信息
         */
        public function httpHeaders()
        {
            $headers = [];

            $grav = Grav::instance();
            $format = $this->templateFormat();
            $cache_control = $this->cacheControl();
            $expires = $this->expires();

            // 设置 Content-Type 头
            $headers['Content-Type'] = Utils::getMimeByExtension($format, 'text/html');

            // 如果设置了 Expires，计算过期日期
            if ($expires > 0) {
                $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
                if (!$cache_control) {
                    $headers['Cache-Control'] = 'max-age=' . $expires;
                }
                $headers['Expires'] = $expires_date;
            }

            // 设置 Cache-Control 头
            if ($cache_control) {
                $headers['Cache-Control'] = strtolower($cache_control);
            }

            // 设置 Last-Modified 头
            if ($this->lastModified()) {
                $last_modified = $this->modified();
                foreach ($this->children()->modular() as $cpage) {
                    $modular_mtime = $cpage->modified();
                    if ($modular_mtime > $last_modified) {
                        $last_modified = $modular_mtime;
                    }
                }

                $last_modified_date = gmdate('D, d M Y H:i:s', $last_modified) . ' GMT';
                $headers['Last-Modified'] = $last_modified_date;
            }

            // 如果启用了 ETag，则设置 ETag 头
            if ($this->eTag()) {
                $headers['ETag'] = '1';
            }

            // 如果配置了 Vary: Accept-Encoding，则设置此头
            if ($grav['config']->get('system.pages.vary_accept_encoding', false)) {
                $headers['Vary'] = 'Accept-Encoding';
            }

            // 触发 onPageHeaders 事件，允许扩展添加或修改头信息
            $headers_obj = (object) $headers;
            Grav::instance()->fireEvent('onPageHeaders', new Event(['headers' => $headers_obj]));

            return (array)$headers_obj;
        }

        /**
         * 获取页面摘要
         *
         * @param int|null $size 最大摘要大小
         * @param bool $textOnly 仅计算文本大小
         * @return string 页面摘要
         */
        public function summary($size = null, $textOnly = false)
        {
            $config = (array)Grav::instance()['config']->get('site.summary');
            if (isset($this->header->summary)) {
                $config = array_merge($config, $this->header->summary);
            }

            // 根据站点配置返回摘要
            if (!$config['enabled']) {
                return $this->content();
            }

            // 设置变量以根据页面或自定义摘要处理摘要
            if ($this->summary === null) {
                $content = $textOnly ? strip_tags($this->content()) : $this->content();
                $summary_size = $this->summary_size;
            } else {
                $content = $textOnly ? strip_tags($this->summary) : $this->summary;
                $summary_size = mb_strwidth($content, 'utf-8');
            }

            // 根据摘要格式返回摘要
            $format = $config['format'];
            // 如果格式不正确或未知，返回整个页面内容
            if (!in_array($format, ['short', 'long'])) {
                return $content;
            }
            if (($format === 'short') && isset($summary_size)) {
                // 截取字符串
                if (mb_strwidth($content, 'utf8') > $summary_size) {
                    return mb_substr($content, 0, $summary_size);
                }

                return $content;
            }

            // 从站点配置获取摘要大小
            if ($size === null) {
                $size = $config['size'];
            }

            // 如果大小为零，返回整个页面内容
            if ($size === 0) {
                return $content;
            }
            // 如果大小无效，使用默认值 300
            if (!is_numeric($size) || ($size < 0)) {
                $size = 300;
            }

            // 仅返回纯文本，不包含 HTML 标签
            if ($textOnly) {
                if (mb_strwidth($content, 'utf-8') <= $size) {
                    return $content;
                }

                return mb_strimwidth($content, 0, $size, '…', 'UTF-8');
            }

            $summary = Utils::truncateHtml($content, $size);

            return html_entity_decode($summary, ENT_COMPAT | ENT_HTML401, 'UTF-8');
        }

        /**
         * 设置页面摘要
         *
         * @param string $summary 摘要内容
         */
        public function setSummary($summary)
        {
            $this->summary = $summary;
        }

        /**
         * 获取或设置页面内容
         *
         * @param  string|null $var 页面内容
         * @return string      页面内容
         */
        public function content($var = null)
        {
            if ($var !== null) {
                $this->raw_content = $var;

                // 更新文件对象
                $file = $this->file();
                if ($file) {
                    $file->markdown($var);
                }

                // 强制重新处理
                $this->id(time() . md5($this->filePath()));
                $this->content = null;
            }
            // 如果没有内容，进行处理
            if ($this->content === null) {
                // 获取媒体
                $this->media();

                /** @var Config $config */
                $config = Grav::instance()['config'];

                // 加载缓存内容
                /** @var Cache $cache */
                $cache = Grav::instance()['cache'];
                $cache_id = md5('page' . $this->getCacheKey());
                $content_obj = $cache->fetch($cache_id);

                if (is_array($content_obj)) {
                    $this->content = $content_obj['content'];
                    $this->content_meta = $content_obj['content_meta'];
                } else {
                    $this->content = $content_obj;
                }


                $process_markdown = $this->shouldProcess('markdown');
                $process_twig = $this->shouldProcess('twig') || $this->modularTwig();

                $cache_enable = $this->header->cache_enable ?? $config->get(
                    'system.cache.enabled',
                    true
                );
                $twig_first = $this->header->twig_first ?? $config->get(
                    'system.pages.twig_first',
                    false
                );

                // 永不缓存 Twig 意味着总是在内容之后运行
                $never_cache_twig = $this->header->never_cache_twig ?? $config->get(
                    'system.pages.never_cache_twig',
                    true
                );

                // 如果没有缓存内容，运行所有处理
                if ($never_cache_twig) {
                    if ($this->content === false || $cache_enable === false) {
                        $this->content = $this->raw_content;
                        Grav::instance()->fireEvent('onPageContentRaw', new Event(['page' => $this]));

                        if ($process_markdown) {
                            $this->processMarkdown();
                        }

                        // 内容已处理但尚未缓存
                        Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                        if ($cache_enable) {
                            $this->cachePageContent();
                        }
                    }

                    if ($process_twig) {
                        $this->processTwig();
                    }
                } else {
                    if ($this->content === false || $cache_enable === false) {
                        $this->content = $this->raw_content;
                        Grav::instance()->fireEvent('onPageContentRaw', new Event(['page' => $this]));

                        if ($twig_first) {
                            if ($process_twig) {
                                $this->processTwig();
                            }
                            if ($process_markdown) {
                                $this->processMarkdown();
                            }

                            // 内容已处理但尚未缓存
                            Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));
                        } else {
                            if ($process_markdown) {
                                $this->processMarkdown($process_twig);
                            }

                            // 内容已处理但尚未缓存
                            Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                            if ($process_twig) {
                                $this->processTwig();
                            }
                        }

                        if ($cache_enable) {
                            $this->cachePageContent();
                        }
                    }
                }

                // 处理摘要分隔符
                $delimiter = $config->get('site.summary.delimiter', '===');
                $divider_pos = mb_strpos($this->content, "<p>{$delimiter}</p>");
                if ($divider_pos !== false) {
                    $this->summary_size = $divider_pos;
                    $this->content = str_replace("<p>{$delimiter}</p>", '', $this->content);
                }

                // 触发 onPageContent 事件，允许扩展进行后续操作
                Grav::instance()->fireEvent('onPageContent', new Event(['page' => $this]));
            }

            return $this->content;
        }

        /**
         * 获取页面内容的元数据数组，并在必要时初始化内容
         *
         * @return mixed 页面内容的元数据
         */
        public function contentMeta()
        {
            if ($this->content === null) {
                $this->content();
            }

            return $this->getContentMeta();
        }

        /**
         * 向页面的 contentMeta 数组中添加一个条目
         *
         * @param string $name 元数据名称
         * @param mixed $value 元数据值
         */
        public function addContentMeta($name, $value)
        {
            $this->content_meta[$name] = $value;
        }

        /**
         * 获取页面内容的元数据
         *
         * @param string|null $name 可选，特定元数据名称
         *
         * @return mixed|null 如果指定名称，则返回对应的元数据，否则返回整个元数据数组
         */
        public function getContentMeta($name = null)
        {
            if ($name) {
                return $this->content_meta[$name] ?? null;
            }

            return $this->content_meta;
        }

        /**
         * 设置页面内容的元数据数组
         *
         * @param array $content_meta 新的元数据数组
         *
         * @return array 更新后的元数据数组
         */
        public function setContentMeta($content_meta)
        {
            return $this->content_meta = $content_meta;
        }

        /**
         * 处理 Markdown 内容。根据配置使用 Parsedown 或 ParsedownExtra
         *
         * @param bool $keepTwig 如果为 true，内容中的 Twig 标签将不会被处理
         * @return void
         */
        protected function processMarkdown(bool $keepTwig = false)
        {
            /** @var Config $config */
            $config = Grav::instance()['config'];

            $markdownDefaults = (array)$config->get('system.pages.markdown');
            if (isset($this->header()->markdown)) {
                $markdownDefaults = array_merge($markdownDefaults, $this->header()->markdown);
            }

            // pages.markdown_extra 已弃用，但仍需检查
            if (!isset($markdownDefaults['extra']) && (isset($this->markdown_extra) || $config->get('system.pages.markdown_extra') !== null)) {
                user_error('配置选项 \'system.pages.markdown_extra\' 自 Grav 1.5 起已弃用，请使用 \'system.pages.markdown.extra\' 代替', E_USER_DEPRECATED);

                $markdownDefaults['extra'] = $this->markdown_extra ?: $config->get('system.pages.markdown_extra');
            }

            $extra = $markdownDefaults['extra'] ?? false;
            $defaults = [
                'markdown' => $markdownDefaults,
                'images' => $config->get('system.images', [])
            ];

            $excerpts = new Excerpts($this, $defaults);

            // 初始化所选的 Parsedown 变体
            if ($extra) {
                $parsedown = new ParsedownExtra($excerpts);
            } else {
                $parsedown = new Parsedown($excerpts);
            }

            $content = $this->content;
            if ($keepTwig) {
                $token = [
                    '/' . Utils::generateRandomString(3),
                    Utils::generateRandomString(3) . '/'
                ];
                // Base64 编码任何 Twig 代码
                $content = preg_replace_callback(
                    ['/({#.*?#})/mu', '/({{.*?}})/mu', '/({%.*?%})/mu'],
                    static function ($matches) use ($token) { return $token[0] . base64_encode($matches[1]) . $token[1]; },
                    $content
                );
            }

            $content = $parsedown->text($content);

            if ($keepTwig) {
                // Base64 解码已编码的 Twig 代码
                $content = preg_replace_callback(
                    ['`' . $token[0] . '([A-Za-z0-9+/]+={0,2})' . $token[1] . '`mu'],
                    static function ($matches) { return base64_decode($matches[1]); },
                    $content
                );
            }

            $this->content = $content;
        }

        /**
         * 处理页面的 Twig 内容
         *
         * @return void
         */
        private function processTwig()
        {
            /** @var Twig $twig */
            $twig = Grav::instance()['twig'];
            $this->content = $twig->processPage($this, $this->content);
        }

        /**
         * 触发 onPageContentProcessed 事件，并使用页面的唯一 ID 缓存页面内容
         *
         * @return void
         */
        public function cachePageContent()
        {
            /** @var Cache $cache */
            $cache = Grav::instance()['cache'];
            $cache_id = md5('page' . $this->getCacheKey());
            $cache->save($cache_id, ['content' => $this->content, 'content_meta' => $this->content_meta]);
        }

        /**
         * 在 onPageContentProcessed 事件中使用，用于获取原始页面内容
         *
         * @return string 当前页面内容
         */
        public function getRawContent()
        {
            return $this->content;
        }

        /**
         * 在 onPageContentProcessed 事件中使用，用于设置原始页面内容
         *
         * @param string|null $content 新的页面内容
         * @return void
         */
        public function setRawContent($content)
        {
            $this->content = $content ?? '';
        }

        /**
         * 获取或设置页面变量值（主要用于创建编辑表单）
         *
         * @param string $name 变量名称
         * @param mixed $default 默认值
         * @return mixed 变量值或默认值
         */
        public function value($name, $default = null)
        {
            if ($name === 'content') {
                return $this->raw_content;
            }
            if ($name === 'route') {
                $parent = $this->parent();

                return $parent ? $parent->rawRoute() : '';
            }
            if ($name === 'order') {
                $order = $this->order();

                return $order ? (int)$this->order() : '';
            }
            if ($name === 'ordering') {
                return (bool)$this->order();
            }
            if ($name === 'folder') {
                return preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder);
            }
            if ($name === 'slug') {
                return $this->slug();
            }
            if ($name === 'name') {
                $name = $this->name();
                $language = $this->language() ? '.' . $this->language() : '';
                $pattern = '%(' . preg_quote($language, '%') . ')?\.md$%';
                $name = preg_replace($pattern, '', $name);

                if ($this->isModule()) {
                    return 'modular/' . $name;
                }

                return $name;
            }
            if ($name === 'media') {
                return $this->media()->all();
            }
            if ($name === 'media.file') {
                return $this->media()->files();
            }
            if ($name === 'media.video') {
                return $this->media()->videos();
            }
            if ($name === 'media.image') {
                return $this->media()->images();
            }
            if ($name === 'media.audio') {
                return $this->media()->audios();
            }

            $path = explode('.', $name);
            $scope = array_shift($path);

            if ($name === 'frontmatter') {
                return $this->frontmatter;
            }

            if ($scope === 'header') {
                $current = $this->header();
                foreach ($path as $field) {
                    if (is_object($current) && isset($current->{$field})) {
                        $current = $current->{$field};
                    } elseif (is_array($current) && isset($current[$field])) {
                        $current = $current[$field];
                    } else {
                        return $default;
                    }
                }

                return $current;
            }

            return $default;
        }

        /**
         * 获取或设置页面的原始 Markdown 内容
         *
         * @param string|null $var 原始 Markdown 内容
         * @return string 原始 Markdown 内容
         */
        public function rawMarkdown($var = null)
        {
            if ($var !== null) {
                $this->raw_content = $var;
            }

            return $this->raw_content;
        }

        /**
         * 判断页面是否已翻译
         *
         * @return bool 如果页面已初始化则返回 true
         * @internal
         */
        public function translated(): bool
        {
            return $this->initialized;
        }

        /**
         * 获取页面的文件对象
         *
         * @return MarkdownFile|null 页面对应的 MarkdownFile 对象
         */
        public function file()
        {
            if ($this->name) {
                return MarkdownFile::instance($this->filePath());
            }

            return null;
        }

        /**
         * 保存页面，如果页面有关联的文件
         *
         * @param bool|array $reorder 内部使用，用于决定是否重新排序
         */
        public function save($reorder = true)
        {
            // 如果需要，执行移动或复制操作
            $this->doRelocation();

            $file = $this->file();
            if ($file) {
                $file->filename($this->filePath());
                $file->header((array)$this->header());
                $file->markdown($this->raw_content);
                $file->save();
            }

            // 如果需要，执行重新排序
            if ($reorder && is_array($reorder)) {
                $this->doReorder($reorder);
            }

            // 通知 Flex Pages 清除缓存
            /** @var Flex|null $flex */
            $flex = Grav::instance()['flex'] ?? null;
            $directory = $flex ? $flex->getDirectory('pages') : null;
            if (null !== $directory) {
                $directory->clearCache();
            }

            $this->_original = null;
        }

        /**
         * 准备将页面移动到新位置。移动页面及其所有子页面。
         * 需要调用 $this->save() 来执行移动操作。
         *
         * @param PageInterface $parent 新的父级页面
         * @return $this
         * @throws RuntimeException 如果尝试将页面设置为自身或其子页面的父级
         */
        public function move(PageInterface $parent)
        {
            if (!$this->_original) {
                $clone = clone $this;
                $this->_original = $clone;
            }

            $this->_action = 'move';

            if ($this->route() === $parent->route()) {
                throw new RuntimeException('失败：无法将页面的父级设置为自身');
            }
            if (Utils::startsWith($parent->rawRoute(), $this->rawRoute())) {
                throw new RuntimeException('失败：无法将页面的父级设置为当前页面的子页面');
            }

            $this->parent($parent);
            $this->id(time() . md5($this->filePath()));

            if ($parent->path()) {
                $this->path($parent->path() . '/' . $this->folder());
            }

            if ($parent->route()) {
                $this->route($parent->route() . '/' . $this->slug());
            } else {
                $this->route(Grav::instance()['pages']->root()->route() . '/' . $this->slug());
            }

            $this->raw_route = null;

            return $this;
        }

        /**
         * 准备复制页面。复制页面及其所有子页面。
         * 返回一个新的 Page 对象用于复制。
         * 需要调用 $this->save() 来执行复制操作。
         *
         * @param PageInterface $parent 新的父级页面
         * @return $this
         */
        public function copy(PageInterface $parent)
        {
            $this->move($parent);
            $this->_action = 'copy';

            return $this;
        }

        /**
         * 获取页面的蓝图
         *
         * @return Blueprint 页面蓝图
         */
        public function blueprints()
        {
            $grav = Grav::instance();

            /** @var Pages $pages */
            $pages = $grav['pages'];

            $blueprint = $pages->blueprints($this->blueprintName());
            $fields = $blueprint->fields();
            $edit_mode = isset($grav['admin']) ? $grav['config']->get('plugins.admin.edit_mode') : null;

            // 如果没有特定字段，使用默认蓝图
            if (empty($fields) && ($edit_mode === 'auto' || $edit_mode === 'normal')) {
                $blueprint = $pages->blueprints('default');
            }

            // 如果是专家模式，使用更高级的蓝图
            if (!empty($fields) && $edit_mode === 'expert') {
                $blueprint = $pages->blueprints('');
            }

            return $blueprint;
        }

        /**
         * 获取页面的蓝图
         *
         * @param string $name 未使用参数，保留接口兼容性
         * @return Blueprint 返回页面蓝图
         */
        public function getBlueprint(string $name = '')
        {
            return $this->blueprints();
        }

        /**
         * 获取页面的蓝图名称。如果在 POST 请求中设置了 blueprint 字段，则使用该值
         *
         * @return string 页面蓝图名称
         */
        public function blueprintName()
        {
            if (!isset($_POST['blueprint'])) {
                return $this->template();
            }

            $post_value = $_POST['blueprint'];
            $sanitized_value = htmlspecialchars(strip_tags($post_value), ENT_QUOTES, 'UTF-8');

            return $sanitized_value ?: $this->template();
        }

        /**
         * 验证页面的 header 是否符合蓝图的定义
         *
         * @return void
         * @throws Exception 如果验证失败
         */
        public function validate()
        {
            $blueprints = $this->blueprints();
            $blueprints->validate($this->toArray());
        }

        /**
         * 过滤页面的 header，移除非法内容
         *
         * @return void
         */
        public function filter()
        {
            $blueprints = $this->blueprints();
            $values = $blueprints->filter($this->toArray());
            if ($values && isset($values['header'])) {
                $this->header($values['header']);
            }
        }

        /**
         * 获取页面 header 中未定义的变量
         *
         * @return array 未定义的 header 变量
         */
        public function extra()
        {
            $blueprints = $this->blueprints();

            return $blueprints->extra($this->toArray()['header'], 'header.');
        }

        /**
         * 将页面转换为数组
         *
         * @return array 页面数组表示
         */
        public function toArray()
        {
            return [
                'header' => (array)$this->header(),
                'content' => (string)$this->value('content')
            ];
        }

        /**
         * 将页面转换为 YAML 编码的字符串
         *
         * @return string YAML 编码的页面内容
         */
        public function toYaml()
        {
            return Yaml::dump($this->toArray(), 20);
        }

        /**
         * 将页面转换为 JSON 编码的字符串
         *
         * @return string JSON 编码的页面内容
         */
        public function toJson()
        {
            return json_encode($this->toArray());
        }

        /**
         * 获取页面的缓存键
         *
         * @return string 缓存键
         */
        public function getCacheKey(): string
        {
            return $this->id();
        }

        /**
         * 获取或设置页面关联的媒体
         *
         * @param  Media|null $var 媒体对象
         * @return Media      关联的媒体对象
         */
        public function media($var = null)
        {
            if ($var) {
                $this->setMedia($var);
            }

            /** @var Media $media */
            $media = $this->getMedia();

            return $media;
        }

        /**
         * 获取关联媒体的文件系统路径
         *
         * @return string|null 媒体文件夹路径
         */
        public function getMediaFolder()
        {
            return $this->path();
        }

        /**
         * 获取关联媒体的显示顺序
         *
         * @return array 空数组表示默认排序
         */
        public function getMediaOrder()
        {
            $header = $this->header();

            return isset($header->media_order) ? array_map('trim', explode(',', (string)$header->media_order)) : [];
        }

        /**
         * 获取或设置页面的名称。如果未设置名称，则返回 'default.md'
         *
         * @param  string|null $var 页面名称
         * @return string      页面名称
         */
        public function name($var = null)
        {
            if ($var !== null) {
                $this->name = $var;
            }

            return $this->name ?: 'default.md';
        }

        /**
         * 获取子页面类型
         *
         * @return string 子页面类型
         */
        public function childType()
        {
            return isset($this->header->child_type) ? (string)$this->header->child_type : '';
        }

        /**
         * 获取或设置页面的模板名称。如果未设置，则根据文件名推断模板名称
         *
         * @param  string|null $var 模板名称
         * @return string      模板名称
         */
        public function template($var = null)
        {
            if ($var !== null) {
                $this->template = $var;
            }
            if (empty($this->template)) {
                $this->template = ($this->isModule() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name());
            }

            return $this->template;
        }

        /**
         * 获取或设置页面的模板格式，通常用于 URL 扩展名（如 .html, .json）
         *
         * @param string|null $var 模板格式
         * @return string      模板格式
         */
        public function templateFormat($var = null)
        {
            if (null !== $var) {
                $this->template_format = is_string($var) ? $var : null;
            }

            if (!isset($this->template_format)) {
                $this->template_format = ltrim($this->header->append_url_extension ?? Utils::getPageFormat(), '.');
            }

            return $this->template_format;
        }

        /**
         * 获取或设置文件扩展名
         *
         * @param string|null $var 文件扩展名
         * @return string      文件扩展名
         */
        public function extension($var = null)
        {
            if ($var !== null) {
                $this->extension = $var;
            }
            if (empty($this->extension)) {
                $this->extension = '.' . Utils::pathinfo($this->name(), PATHINFO_EXTENSION);
            }

            return $this->extension;
        }

        /**
         * 获取页面的 URL 扩展名，基于页面的 `url_extension` 配置或系统配置
         *
         * @return string 页面 URL 扩展名，例如 `.html`
         */
        public function urlExtension()
        {
            if ($this->home()) {
                return '';
            }

            // 如果未在页面中设置，使用系统配置的值
            if (null === $this->url_extension) {
                $this->url_extension = Grav::instance()['config']->get('system.pages.append_url_extension', '');
            }

            return $this->url_extension;
        }

        /**
         * 获取或设置页面的过期时间。如果未设置，则返回默认值
         *
         * @param  int|null $var 过期时间（秒）
         * @return int      过期时间
         */
        public function expires($var = null)
        {
            if ($var !== null) {
                $this->expires = $var;
            }

            return $this->expires ?? Grav::instance()['config']->get('system.pages.expires');
        }

        /**
         * 获取或设置页面的 Cache-Control 头
         *
         * @param string|null $var Cache-Control 头值
         * @return string|null Cache-Control 头值
         */
        public function cacheControl($var = null)
        {
            if ($var !== null) {
                $this->cache_control = $var;
            }

            return $this->cache_control ?? Grav::instance()['config']->get('system.pages.cache_control');
        }

        /**
         * 获取或设置页面的标题。如果未设置，则使用 slug 作为标题
         *
         * @param  string|null $var 页面标题
         * @return string      页面标题
         */
        public function title($var = null)
        {
            if ($var !== null) {
                $this->title = $var;
            }
            if (empty($this->title)) {
                $this->title = ucfirst($this->slug());
            }

            return $this->title;
        }

        /**
         * 获取或设置页面的菜单名称。如果未设置，则使用标题
         *
         * @param  string|null $var 菜单名称
         * @return string      菜单名称
         */
        public function menu($var = null)
        {
            if ($var !== null) {
                $this->menu = $var;
            }
            if (empty($this->menu)) {
                $this->menu = $this->title();
            }

            return $this->menu;
        }

        /**
         * 获取或设置页面在导航中是否可见
         *
         * @param  bool|null $var 是否可见
         * @return bool      是否可见
         */
        public function visible($var = null)
        {
            if ($var !== null) {
                $this->visible = (bool)$var;
            }

            if ($this->visible === null) {
                // 如果文件夹名与 slug 不同，设置页面可见
                // 例如 folder = 01.Home 且 slug = Home
                if (preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder)) {
                    $this->visible = true;
                } else {
                    $this->visible = false;
                }
            }

            return $this->visible;
        }

        /**
         * 获取或设置页面是否已发布
         *
         * @param  bool|null $var 是否已发布
         * @return bool      是否已发布
         */
        public function published($var = null)
        {
            if ($var !== null) {
                $this->published = (bool)$var;
            }

            // 如果未发布，则在导航中也不可见
            if ($this->published === false) {
                $this->visible = false;
            }

            return $this->published;
        }

        /**
         * 获取或设置页面的发布日期
         *
         * @param  string|null $var 发布日期字符串
         * @return int         发布日期的时间戳
         */
        public function publishDate($var = null)
        {
            if ($var !== null) {
                $this->publish_date = Utils::date2timestamp($var, $this->dateformat);
            }

            return $this->publish_date;
        }

        /**
         * 获取或设置页面的取消发布日期
         *
         * @param  string|null $var 取消发布日期字符串
         * @return int|null         取消发布日期的时间戳
         */
        public function unpublishDate($var = null)
        {
            if ($var !== null) {
                $this->unpublish_date = Utils::date2timestamp($var, $this->dateformat);
            }

            return $this->unpublish_date;
        }

        /**
         * 获取或设置页面是否可路由。页面必须是可路由且已发布才能通过 URL 访问
         *
         * @param  bool|null $var 是否可路由
         * @return bool      是否可路由
         */
        public function routable($var = null)
        {
            if ($var !== null) {
                $this->routable = (bool)$var;
            }

            return $this->routable && $this->published();
        }

        /**
         * 获取或设置页面是否启用 SSL
         *
         * @param bool|null $var 是否启用 SSL
         * @return bool      是否启用 SSL
         */
        public function ssl($var = null)
        {
            if ($var !== null) {
                $this->ssl = (bool)$var;
            }

            return $this->ssl;
        }

        /**
         * 获取或设置页面的处理配置。这是一个多维数组，包含如 "markdown" => true 的键值对
         *
         * @param  array|null $var 处理配置数组
         * @return array      处理配置数组
         */
        public function process($var = null)
        {
            if ($var !== null) {
                $this->process = (array)$var;
            }

            return $this->process;
        }

        /**
         * 获取页面调试器的配置状态
         *
         * @return bool 调试器是否启用
         */
        public function debugger()
        {
            return !(isset($this->debugger) && $this->debugger === false);
        }

        /**
         * 合并页面的元数据标签，并构建一个元数据对象数组，可在页面中渲染
         *
         * @param  array|null $var 新的元数据值
         * @return array      当前页面的元数据数组
         */
        public function metadata($var = null)
        {
            if ($var !== null) {
                $this->metadata = (array)$var;
            }

            // 如果还没有元数据，进行处理
            if (null === $this->metadata) {
                $header_tag_http_equivs = ['content-type', 'default-style', 'refresh', 'x-ua-compatible', 'content-security-policy'];

                $this->metadata = [];

                // 设置 Generator 标签
                $metadata = [
                    'generator' => 'GravCMS'
                ];

                $config = Grav::instance()['config'];

                $escape = !$config->get('system.strict_mode.twig_compat', false) || $config->get('system.twig.autoescape', true);

                // 获取页面的初始元数据
                $metadata = array_merge($metadata, $config->get('site.metadata', []));

                if (isset($this->header->metadata) && is_array($this->header->metadata)) {
                    // 将 site.metadata 与页面的元数据合并
                    $metadata = array_merge($metadata, $this->header->metadata);
                }

                // 构建元数据对象数组
                foreach ((array)$metadata as $key => $value) {
                    // 将键名转为小写
                    $key = strtolower($key);
                    // 如果是属性类型的元数据，如 "og", "twitter", "facebook" 等
                    // 兼容嵌套数组的元数据
                    if (is_array($value)) {
                        foreach ($value as $property => $prop_value) {
                            $prop_key = $key . ':' . $property;
                            $this->metadata[$prop_key] = [
                                'name' => $prop_key,
                                'property' => $prop_key,
                                'content' => $escape ? htmlspecialchars($prop_value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $prop_value
                            ];
                        }
                    } else {
                        // 如果是标准的元数据类型
                        if ($value) {
                            if (in_array($key, $header_tag_http_equivs, true)) {
                                $this->metadata[$key] = [
                                    'http_equiv' => $key,
                                    'content' => $escape ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value
                                ];
                            } elseif ($key === 'charset') {
                                $this->metadata[$key] = ['charset' => $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value];
                            } else {
                                // 如果是带分隔符的社交元数据，作为 property 处理
                                $separator = strpos($key, ':');
                                $hasSeparator = $separator && $separator < strlen($key) - 1;
                                $entry = [
                                    'content' => $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value
                                ];

                                if ($hasSeparator && !Utils::startsWith($key, ['twitter', 'flattr','fediverse'])) {
                                    $entry['property'] = $key;
                                } else {
                                    $entry['name'] = $key;
                                }

                                $this->metadata[$key] = $entry;
                            }
                        }
                    }
                }
            }

            return $this->metadata;
        }

        /**
         * 重置元数据并从 header 中重新获取
         */
        public function resetMetadata()
        {
            $this->metadata = null;
        }

        /**
         * 获取或设置页面的 slug。Slug 用于 URL 路由。如果未设置，则使用父文件夹名
         *
         * @param  string|null $var 页面 slug，例如 'my-blog'
         * @return string      页面 slug
         */
        public function slug($var = null)
        {
            if ($var !== null && $var !== '') {
                $this->slug = $var;
            }

            if (empty($this->slug)) {
                $this->slug = $this->adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', (string) $this->folder)) ?: null;
            }

            return $this->slug;
        }

        /**
         * 获取或设置页面的排序编号
         *
         * @param int|null $var 排序编号
         * @return string|bool 排序编号或 false
         */
        public function order($var = null)
        {
            if ($var !== null) {
                $order = $var ? sprintf('%02d.', (int)$var) : '';
                $this->folder($order . preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder));

                return $order;
            }

            preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder, $order);

            return $order[0] ?? false;
        }

        /**
         * 获取页面的 URL，等同于 url() 方法
         *
         * @param bool $include_host 是否包含主机名
         * @return string 页面链接
         */
        public function link($include_host = false)
        {
            return $this->url($include_host);
        }

        /**
         * 获取页面的 Permalink（包含主机名）
         *
         * @return string 页面 Permalink
         */
        public function permalink()
        {
            return $this->url(true, false, true, true);
        }

        /**
         * 获取页面的规范 URL
         *
         * @param bool $include_lang 是否包含语言代码
         * @return string 页面规范 URL
         */
        public function canonical($include_lang = true)
        {
            return $this->url(true, true, $include_lang);
        }

        /**
         * 获取页面的 URL
         *
         * @param bool $include_host 是否包含主机名
         * @param bool $canonical    是否返回规范 URL
         * @param bool $include_base 是否包含基础 URL（多站点和语言代码）
         * @param bool $raw_route    是否使用原始路由
         * @return string 页面 URL
         */
        public function url($include_host = false, $canonical = false, $include_base = true, $raw_route = false)
        {
            // 如果设置了 external_url，则覆盖任何 URL
            if (isset($this->external_url)) {
                return $this->external_url;
            }

            $grav = Grav::instance();

            /** @var Pages $pages */
            $pages = $grav['pages'];

            /** @var Config $config */
            $config = $grav['config'];

            // 获取基础路由（多站点基础和语言）
            $route = $include_base ? $pages->baseRoute() : '';

            // 如果配置了绝对 URL，并且不包含主机名，则需要包含主机名
            if (!$include_host && $config->get('system.absolute_urls', false)) {
                $include_host = true;
            }

            if ($canonical) {
                $route .= $this->routeCanonical();
            } elseif ($raw_route) {
                $route .= $this->rawRoute();
            } else {
                $route .= $this->route();
            }

            /** @var Uri $uri */
            $uri = $grav['uri'];
            $url = $uri->rootUrl($include_host) . '/' . trim($route, '/') . $this->urlExtension();

            return Uri::filterPath($url);
        }

        /**
         * 获取或设置页面的路由
         *
         * @param  string|null $var 设置新的路由
         * @return string|null  页面路由
         */
        public function route($var = null)
        {
            if ($var !== null) {
                $this->route = $var;
            }

            if (empty($this->route)) {
                $baseRoute = null;

                // 根据父级的 slug 计算路由
                $parent = $this->parent();
                if (isset($parent)) {
                    if ($this->hide_home_route && $parent->route() === $this->home_route) {
                        $baseRoute = '';
                    } else {
                        $baseRoute = (string)$parent->route();
                    }
                }

                $this->route = isset($baseRoute) ? $baseRoute . '/' . $this->slug() : null;

                if (!empty($this->routes) && isset($this->routes['default'])) {
                    $this->routes['aliases'][] = $this->route;
                    $this->route = $this->routes['default'];

                    return $this->route;
                }
            }

            return $this->route;
        }

        /**
         * 清除路由和 slug，以便下次调用时重新生成
         */
        public function unsetRouteSlug()
        {
            unset($this->route, $this->slug);
        }

        /**
         * 获取或设置页面的原始路由
         *
         * @param string|null $var 设置新的原始路由
         * @return null|string 页面原始路由
         */
        public function rawRoute($var = null)
        {
            if ($var !== null) {
                $this->raw_route = $var;
            }

            if (empty($this->raw_route)) {
                $parent = $this->parent();
                $baseRoute = $parent ? (string)$parent->rawRoute() : null;

                $slug = $this->adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder));

                $this->raw_route = isset($baseRoute) ? $baseRoute . '/' . $slug : null;
            }

            return $this->raw_route;
        }

        /**
         * 获取页面的路由别名
         *
         * @param  array|null $var 新的路由别名数组
         * @return array  页面路由别名数组
         */
        public function routeAliases($var = null)
        {
            if ($var !== null) {
                $this->routes['aliases'] = (array)$var;
            }

            if (!empty($this->routes) && isset($this->routes['aliases'])) {
                return $this->routes['aliases'];
            }

            return [];
        }

        /**
         * 获取页面的规范路由。如果设置了规范路由，则使用该值，否则使用默认路由
         *
         * @param string|null $var 设置新的规范路由
         * @return bool|string 规范路由或默认路由
         */
        public function routeCanonical($var = null)
        {
            if ($var !== null) {
                $this->routes['canonical'] = $var;
            }

            if (!empty($this->routes) && isset($this->routes['canonical'])) {
                return $this->routes['canonical'];
            }

            return $this->route();
        }

        /**
         * 获取或设置页面的唯一标识符
         *
         * @param  string|null $var 设置新的唯一标识符
         * @return string      页面唯一标识符
         */
        public function id($var = null)
        {
            if (null === $this->id) {
                // 设置唯一 ID 以避免缓存冲突
                $var = time() . md5($this->filePath());
            }
            if ($var !== null) {
                // 根据当前语言设置唯一 ID
                $active_lang = Grav::instance()['language']->getLanguage() ?: '';
                $id = $active_lang . $var;
                $this->id = $id;
            }

            return $this->id;
        }

        /**
         * 获取或设置页面的最后修改时间
         *
         * @param  int|null $var 修改时间的时间戳
         * @return int      修改时间的时间戳
         */
        public function modified($var = null)
        {
            if ($var !== null) {
                $this->modified = $var;
            }

            return $this->modified;
        }

        /**
         * 获取或设置页面的重定向 URL
         *
         * @param  string|null $var 重定向 URL
         * @return string|null 重定向 URL
         */
        public function redirect($var = null)
        {
            if ($var !== null) {
                $this->redirect = $var;
            }

            return $this->redirect ?: null;
        }

        /**
         * 获取或设置页面是否启用 ETag
         *
         * @param  bool|null $var 是否启用 ETag
         * @return bool      是否启用 ETag
         */
        public function eTag($var = null): bool
        {
            if ($var !== null) {
                $this->etag = $var;
            }
            if (!isset($this->etag)) {
                $this->etag = (bool)Grav::instance()['config']->get('system.pages.etag');
            }

            return $this->etag ?? false;
        }

        /**
         * 获取或设置页面是否启用 Last-Modified 头
         *
         * @param  bool|null $var 是否启用 Last-Modified
         * @return bool      是否启用 Last-Modified
         */
        public function lastModified($var = null)
        {
            if ($var !== null) {
                $this->last_modified = $var;
            }
            if (!isset($this->last_modified)) {
                $this->last_modified = (bool)Grav::instance()['config']->get('system.pages.last_modified');
            }

            return $this->last_modified;
        }

        /**
         * 获取或设置页面文件路径
         *
         * @param  string|null $var 文件路径
         * @return string|null      文件路径
         */
        public function filePath($var = null)
        {
            if ($var !== null) {
                // 设置页面的文件名
                $this->name = Utils::basename($var);
                // 设置页面的文件夹
                $this->folder = Utils::basename(dirname($var));
                // 设置页面的路径
                $this->path = dirname($var, 2);
            }

            return rtrim($this->path . '/' . $this->folder . '/' . ($this->name() ?: ''), '/');
        }

        /**
         * 获取页面文件的相对路径
         *
         * @return string 相对文件路径
         */
        public function filePathClean()
        {
            return str_replace(GRAV_ROOT . DS, '', $this->filePath());
        }

        /**
         * 获取页面文件的清理路径
         *
         * @return string 清理后的文件路径
         */
        public function relativePagePath()
        {
            return str_replace('/' . $this->name(), '', $this->filePathClean());
        }

        /**
         * 获取或设置页面文件夹路径
         *
         * @param  string|null $var 页面文件夹路径
         * @return string|null      页面文件夹路径
         */
        public function path($var = null)
        {
            if ($var !== null) {
                // 设置页面的文件夹
                $this->folder = Utils::basename($var);
                // 设置页面的路径
                $this->path = dirname($var);
            }

            return $this->path ? $this->path . '/' . $this->folder : null;
        }

        /**
         * 获取或设置页面的文件夹
         *
         * @param string|null $var 页面文件夹
         * @return string|null 页面文件夹
         */
        public function folder($var = null)
        {
            if ($var !== null) {
                $this->folder = $var;
            }

            return $this->folder;
        }

        /**
         * 获取或设置页面的日期
         *
         * @param  string|null $var 日期字符串
         * @return int         日期的时间戳
         */
        public function date($var = null)
        {
            if ($var !== null) {
                $this->date = Utils::date2timestamp($var, $this->dateformat);
            }

            if (!$this->date) {
                $this->date = $this->modified;
            }

            return $this->date;
        }

        /**
         * 获取或设置页面的日期格式
         *
         * @param  string|null $var 日期格式字符串
         * @return string      日期格式字符串
         */
        public function dateformat($var = null)
        {
            if ($var !== null) {
                $this->dateformat = $var;
            }

            return $this->dateformat;
        }

        /**
         * 获取或设置页面的排序方向（已弃用）
         *
         * @param  string|null $var 排序方向（"asc" 或 "desc"）
         * @return string      排序方向
         * @deprecated 1.6
         */
        public function orderDir($var = null)
        {
            //user_error(__CLASS__ . '::' . __FUNCTION__ . '() 自 Grav 1.6 起已弃用', E_USER_DEPRECATED);

            if ($var !== null) {
                $this->order_dir = $var;
            }

            if (empty($this->order_dir)) {
                $this->order_dir = 'asc';
            }

            return $this->order_dir;
        }

        /**
         * 获取或设置页面的排序依据（已弃用）
         *
         * 支持的选项包括 "default", "title", "date", 和 "folder"
         *
         * @param  string|null $var 排序依据
         * @return string      排序依据
         * @deprecated 1.6
         */
        public function orderBy($var = null)
        {
            //user_error(__CLASS__ . '::' . __FUNCTION__ . '() 自 Grav 1.6 起已弃用', E_USER_DEPRECATED);

            if ($var !== null) {
                $this->order_by = $var;
            }

            return $this->order_by;
        }

        /**
         * 获取或设置页面的手动排序（已弃用）
         *
         * @param  string|array|null $var 手动排序数组
         * @return array       手动排序数组
         * @deprecated 1.6
         */
        public function orderManual($var = null)
        {
            //user_error(__CLASS__ . '::' . __FUNCTION__ . '() 自 Grav 1.6 起已弃用', E_USER_DEPRECATED);

            if ($var !== null) {
                $this->order_manual = $var;
            }

            return (array)$this->order_manual;
        }

        /**
         * 获取或设置页面的最大子页面数量（已弃用）
         *
         * @param  int|null $var 最大子页面数量
         * @return int      最大子页面数量
         * @deprecated 1.6
         */
        public function maxCount($var = null)
        {
            //user_error(__CLASS__ . '::' . __FUNCTION__ . '() 自 Grav 1.6 起已弃用', E_USER_DEPRECATED);

            if ($var !== null) {
                $this->max_count = (int)$var;
            }
            if (empty($this->max_count)) {
                /** @var Config $config */
                $config = Grav::instance()['config'];
                $this->max_count = (int)$config->get('system.pages.list.count');
            }

            return $this->max_count;
        }

        /**
         * 获取或设置页面的分类法数组
         *
         * @param  array|null $var 分类法数组
         * @return array      分类法数组
         */
        public function taxonomy($var = null)
        {
            if ($var !== null) {
                // 确保一级分类法为数组
                array_walk($var, static function (&$value) {
                    $value = (array) $value;
                });
                // 确保所有值为字符串
                array_walk_recursive($var, static function (&$value) {
                    $value = (string) $value;
                });
                $this->taxonomy = $var;
            }

            return $this->taxonomy;
        }

        /**
         * 获取或设置页面是否为模块化页面，用于识别页面是否为模块化子页面，需要不同的 Twig 处理
         *
         * @param  bool|null $var 是否为模块化页面
         * @return bool      是否为模块化页面
         */
        public function modularTwig($var = null)
        {
            if ($var !== null) {
                $this->modular_twig = (bool)$var;
                if ($var) {
                    $this->visible(false);
                    // 如果未在 header 中显式设置 routable，则自动设置为不可路由
                    if (empty($this->header->routable)) {
                        $this->routable = false;
                    }
                }
            }

            return $this->modular_twig ?? false;
        }

        /**
         * 获取指定的处理方法是否启用
         *
         * @param  string $process 处理方法名称，例如 "twig" 或 "markdown"
         * @return bool            处理方法是否启用
         */
        public function shouldProcess($process)
        {
            return (bool)($this->process[$process] ?? false);
        }

        /**
         * 获取或设置页面的父级对象
         *
         * @param  PageInterface|null $var 父级页面对象
         * @return PageInterface|null 父级页面对象（如果存在）
         */
        public function parent(PageInterface $var = null)
        {
            if ($var) {
                $this->parent = $var->path();

                return $var;
            }

            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->get($this->parent);
        }

        /**
         * 获取页面的顶级父级对象，可以返回页面自身
         *
         * @return PageInterface 顶级父级页面对象
         */
        public function topParent()
        {
            $topParent = $this;

            while (true) {
                $theParent = $topParent->parent();
                if ($theParent !== null && $theParent->parent() !== null) {
                    $topParent = $theParent;
                } else {
                    break;
                }
            }

            return $topParent;
        }

        /**
         * 获取页面的子页面集合
         *
         * @return PageCollectionInterface|Collection 子页面集合
         */
        public function children()
        {
            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->children($this->path());
        }


        /**
         * 检查此页面是否是其父级中第一个子页面
         *
         * @return bool 如果是第一个子页面则返回 true
         */
        public function isFirst()
        {
            $parent = $this->parent();
            $collection = $parent ? $parent->collection('content', false) : null;
            if ($collection instanceof Collection) {
                return $collection->isFirst($this->path());
            }

            return true;
        }

        /**
         * 检查此页面是否是其父级中最后一个子页面
         *
         * @return bool 如果是最后一个子页面则返回 true
         */
        public function isLast()
        {
            $parent = $this->parent();
            $collection = $parent ? $parent->collection('content', false) : null;
            if ($collection instanceof Collection) {
                return $collection->isLast($this->path());
            }

            return true;
        }

        /**
         * 获取页面的前一个兄弟页面
         *
         * @return PageInterface|null 前一个兄弟页面，或不存在时返回 null
         */
        public function prevSibling()
        {
            return $this->adjacentSibling(-1);
        }

        /**
         * 获取页面的下一个兄弟页面
         *
         * @return PageInterface|null 下一个兄弟页面，或不存在时返回 null
         */
        public function nextSibling()
        {
            return $this->adjacentSibling(1);
        }

        /**
         * 根据方向获取相邻的兄弟页面
         *
         * @param  int $direction -1 获取前一个兄弟，1 获取下一个兄弟
         * @return PageInterface|false 相邻的兄弟页面，或不存在时返回 false
         */
        public function adjacentSibling($direction = 1)
        {
            $parent = $this->parent();
            $collection = $parent ? $parent->collection('content', false) : null;
            if ($collection instanceof Collection) {
                return $collection->adjacentSibling($this->path(), $direction);
            }

            return false;
        }

        /**
         * 获取页面在父级中的当前索引位置
         *
         * @return int|null 页面在父级中的索引，或不存在时返回 null
         */
        public function currentPosition()
        {
            $parent = $this->parent();
            $collection = $parent ? $parent->collection('content', false) : null;
            if ($collection instanceof Collection) {
                return $collection->currentPosition($this->path());
            }

            return 1;
        }

        /**
         * 检查此页面是否为当前请求的活动页面
         *
         * @return bool 如果是活动页面则返回 true
         */
        public function active()
        {
            $uri_path = rtrim(urldecode(Grav::instance()['uri']->path()), '/') ?: '/';
            $routes = Grav::instance()['pages']->routes();

            return isset($routes[$uri_path]) && $routes[$uri_path] === $this->path();
        }

        /**
         * 检查当前 URI 的 URL 是否包含此页面的 URL，即检查是否为活动子页面
         *
         * @return bool 如果是活动子页面则返回 true
         */
        public function activeChild()
        {
            $grav = Grav::instance();
            /** @var Uri $uri */
            $uri = $grav['uri'];
            /** @var Pages $pages */
            $pages = $grav['pages'];
            $uri_path = rtrim(urldecode($uri->path()), '/');
            $routes = $pages->routes();

            if (isset($routes[$uri_path])) {
                $page = $pages->find($uri->route());
                /** @var PageInterface|null $child_page */
                $child_page = $page ? $page->parent() : null;
                while ($child_page && !$child_page->root()) {
                    if ($this->path() === $child_page->path()) {
                        return true;
                    }
                    $child_page = $child_page->parent();
                }
            }

            return false;
        }

        /**
         * 检查此页面是否为配置的首页
         *
         * @return bool 如果是首页则返回 true
         */
        public function home()
        {
            $home = Grav::instance()['config']->get('system.home.alias');

            return $this->route() === $home || $this->rawRoute() === $home;
        }

        /**
         * 检查此页面是否为页面树的根节点
         *
         * @return bool 如果是根节点则返回 true
         */
        public function root()
        {
            return !$this->parent && !$this->name && !$this->visible;
        }

        /**
         * 获取页面的祖先页面
         *
         * @param bool|null $lookup 要查找的父文件夹名称
         * @return PageInterface|null 祖先页面对象，如果不存在则返回 null
         */
        public function ancestor($lookup = null)
        {
            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->ancestor($this->route, $lookup);
        }

        /**
         * 获取或设置页面继承的字段值。当前页面对象将被返回。
         *
         * @param string $field 要继承的字段名称
         * @return PageInterface 继承的页面对象
         */
        public function inherited($field)
        {
            [$inherited, $currentParams] = $this->getInheritedParams($field);

            $this->modifyHeader($field, $currentParams);

            return $inherited;
        }

        /**
         * 获取或设置页面继承的字段值。仅返回第一个找到的祖先字段值。
         *
         * @param string $field 要继承的字段名称
         *
         * @return array 页面继承的字段值
         */
        public function inheritedField($field)
        {
            [$inherited, $currentParams] = $this->getInheritedParams($field);

            return $currentParams;
        }

        /**
         * 共享逻辑方法，用于 inherited() 和 inheritedField()
         *
         * @param string $field 字段名称
         * @return array 包含继承页面对象和当前参数的数组
         */
        protected function getInheritedParams($field)
        {
            $pages = Grav::instance()['pages'];

            /** @var Pages $pages */
            $inherited = $pages->inherited($this->route, $field);
            $inheritedParams = $inherited ? (array)$inherited->value('header.' . $field) : [];
            $currentParams = (array)$this->value('header.' . $field);
            if ($inheritedParams && is_array($inheritedParams)) {
                $currentParams = array_replace_recursive($inheritedParams, $currentParams);
            }

            return [$inherited, $currentParams];
        }

        /**
         * 根据 URL 查找页面
         *
         * @param string $url 要查找的页面 URL
         * @param bool $all 是否查找所有匹配的页面
         *
         * @return PageInterface|null 查找到的页面对象，或不存在时返回 null
         */
        public function find($url, $all = false)
        {
            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->find($url, $all);
        }

        /**
         * 获取当前上下文中的页面集合
         *
         * @param string|array $params 查询参数，可以是页面 header 字段名或参数数组
         * @param bool $pagination 是否启用分页
         *
         * @return PageCollectionInterface|Collection 页面集合
         * @throws InvalidArgumentException 如果参数类型不正确
         */
        public function collection($params = 'content', $pagination = true)
        {
            if (is_string($params)) {
                // 从页面 header 字段名中查找
                $params = (array)$this->value('header.' . $params);
            } elseif (!is_array($params)) {
                throw new InvalidArgumentException('参数应为 header 变量名或参数数组');
            }

            $params['filter'] = ($params['filter'] ?? []) + ['translated' => true];
            $context = [
                'pagination' => $pagination,
                'self' => $this
            ];

            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->getCollection($params, $context);
        }

        /**
         * 根据值评估并获取页面集合
         *
         * @param string|array $value 查询值
         * @param bool $only_published 是否仅包含已发布的页面
         * @return PageCollectionInterface|Collection 页面集合
         */
        public function evaluate($value, $only_published = true)
        {
            $params = [
                'items' => $value,
                'published' => $only_published
            ];
            $context = [
                'event' => false,
                'pagination' => false,
                'url_taxonomy_filters' => false,
                'self' => $this
            ];

            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->getCollection($params, $context);
        }

        /**
         * 检查页面是否为一个具体的页面（有 .md 文件）
         *
         * @return bool 如果是具体页面则返回 true
         */
        public function isPage()
        {
            if ($this->name) {
                return true;
            }

            return false;
        }

        /**
         * 检查页面是否为一个目录（没有 .md 文件）
         *
         * @return bool 如果是目录则返回 true
         */
        public function isDir()
        {
            return !$this->isPage();
        }

        /**
         * 检查页面是否为模块化页面
         *
         * @return bool 如果是模块化页面则返回 true
         */
        public function isModule(): bool
        {
            return $this->modularTwig();
        }

        /**
         * 检查页面是否存在于文件系统中
         *
         * @return bool 如果页面存在则返回 true
         */
        public function exists()
        {
            $file = $this->file();

            return $file && $file->exists();
        }

        /**
         * 检查页面的文件夹是否存在
         *
         * @return bool 如果文件夹存在则返回 true
         */
        public function folderExists()
        {
            return file_exists($this->path());
        }

        /**
         * 清理路径，移除路径中的最后一个片段
         *
         * @param  string $path 要清理的路径
         * @return string       清理后的路径
         */
        protected function cleanPath($path)
        {
            $lastchunk = strrchr($path, DS);
            if (strpos($lastchunk, ':') !== false) {
                $path = str_replace($lastchunk, '', $path);
            }

            return $path;
        }

        /**
         * 根据定义的顺序重新排列所有兄弟页面
         *
         * @param array|null $new_order 新的排序顺序数组
         */
        protected function doReorder($new_order)
        {
            if (!$this->_original) {
                return;
            }

            $pages = Grav::instance()['pages'];
            $pages->init();

            $this->_original->path($this->path());

            $parent = $this->parent();
            $siblings = $parent ? $parent->children() : null;

            if ($siblings) {
                $siblings->order('slug', 'asc', $new_order);

                $counter = 0;

                // 重新排序所有移动的页面
                foreach ($siblings as $slug => $page) {
                    $order = (int)trim($page->order(), '.');
                    $counter++;

                    if ($order) {
                        if ($page->path() === $this->path() && $this->folderExists()) {
                            // 处理当前页面；我们希望更改排序编号，但不更改其他内容
                            $this->order($counter);
                            $this->save(false);
                        } else {
                            // 处理所有其他页面
                            $page = $pages->get($page->path());
                            if ($page && $page->folderExists() && !$page->_action) {
                                $page = $page->move($this->parent());
                                $page->order($counter);
                                $page->save(false);
                            }
                        }
                    }
                }
            }
        }

        /**
         * 移动或复制页面及其子页面到新的文件系统位置
         *
         * @internal
         * @return void
         * @throws Exception 如果移动或复制失败
         */
        protected function doRelocation()
        {
            if (!$this->_original) {
                return;
            }

            if (is_dir($this->_original->path())) {
                if ($this->_action === 'move') {
                    Folder::move($this->_original->path(), $this->path());
                } elseif ($this->_action === 'copy') {
                    Folder::copy($this->_original->path(), $this->path());
                }
            }

            if ($this->name() !== $this->_original->name()) {
                $path = $this->path();
                if (is_file($path . '/' . $this->_original->name())) {
                    rename($path . '/' . $this->_original->name(), $path . '/' . $this->name());
                }
            }
        }

        /**
         * 设置页面的发布状态，基于发布和取消发布日期
         *
         * @return void
         */
        protected function setPublishState()
        {
            // 如果未在 header 中显式设置 published 选项，处理发布日期
            if (Grav::instance()['config']->get('system.pages.publish_dates') && !isset($this->header->published)) {
                // 如果设置了取消发布日期，并且已经过期，则取消发布
                if ($this->unpublishDate()) {
                    if ($this->unpublishDate() < time()) {
                        $this->published(false);
                    } else {
                        $this->published();
                        Grav::instance()['cache']->setLifeTime($this->unpublishDate());
                    }
                }
                // 如果设置了发布日期，并且尚未到达，则取消发布并设置缓存生命周期
                if ($this->publishDate() && $this->publishDate() > time()) {
                    $this->published(false);
                    Grav::instance()['cache']->setLifeTime($this->publishDate());
                }
            }
        }

        /**
         * 调整路由的大小写，基于系统配置
         *
         * @param string $route 要调整的路由
         * @return string 调整后的路由
         */
        protected function adjustRouteCase($route)
        {
            $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

            return $case_insensitive ? mb_strtolower($route) : $route;
        }

        /**
         * 获取未修改的原始页面对象
         *
         * @return PageInterface|null 原始页面对象
         */
        public function getOriginal()
        {
            return $this->_original;
        }

        /**
         * 获取当前操作类型
         *
         * @return string|null 操作类型
         */
        public function getAction()
        {
            return $this->_action;
        }
    }
