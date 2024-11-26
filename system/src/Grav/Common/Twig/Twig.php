<?php
namespace Grav\Common\Twig;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Language\LanguageCodes;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Security;
use Grav\Common\Twig\Exception\TwigException;
use Grav\Common\Twig\Extension\FilesystemExtension;
use Grav\Common\Twig\Extension\GravExtension;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use Twig\Cache\FilesystemCache;
use Twig\DeferredExtension\DeferredExtension;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\FilesystemLoader;
use Twig\Profiler\Profile;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function function_exists;
use function in_array;
use function is_array;

/**
 * Twig 类
 * @package Grav\Common\Twig
 *
 * Twig 类负责初始化和管理 Twig 环境，用于渲染 Grav CMS 中的模板。
 * 它设置 Twig 的加载器链、环境配置、扩展，并处理页面内容的 Twig 处理。
 */
class Twig
{
    /** @var Environment Twig 环境实例 */
    public $twig;
    /** @var array Twig 变量数组，用于传递给模板 */
    public $twig_vars = [];
    /** @var array Twig 模板路径数组 */
    public $twig_paths;
    /** @var string 当前模板名称 */
    public $template;

    /** @var array 插件挂钩到导航的数组 */
    public $plugins_hooked_nav = [];
    /** @var array 插件快速托盘的数组 */
    public $plugins_quick_tray = [];
    /** @var array 插件挂钩到仪表盘顶部小部件的数组 */
    public $plugins_hooked_dashboard_widgets_top = [];
    /** @var array 插件挂钩到仪表盘主小部件的数组 */
    public $plugins_hooked_dashboard_widgets_main = [];

    /** @var Grav Grav 实例 */
    protected $grav;
    /** @var FilesystemLoader 文件系统加载器 */
    protected $loader;
    /** @var ArrayLoader 数组加载器，用于动态添加模板 */
    protected $loaderArray;
    /** @var bool 自动转义设置 */
    protected $autoescape;
    /** @var Profile Twig 分析器配置 */
    protected $profile;

    /**
     * 构造函数
     *
     * @param Grav $grav Grav 实例
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->twig_paths = [];
    }

    /**
     * 初始化 Twig 环境，设置加载器链、环境配置、扩展以及默认 Twig 变量
     *
     * @return $this
     */
    public function init()
    {
        if (null === $this->twig) {
            /** @var Config $config */
            $config = $this->grav['config'];
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];
            /** @var Language $language */
            $language = $this->grav['language'];

            $active_language = $language->getActive();

            // 处理语言模板（如果可用）
            if ($language->enabled()) {
                $lang_templates = $locator->findResource('theme://templates/' . ($active_language ?: $language->getDefault()));
                if ($lang_templates) {
                    $this->twig_paths[] = $lang_templates;
                }
            }

            // 添加主题模板路径
            $this->twig_paths = array_merge($this->twig_paths, $locator->findResources('theme://templates'));

            // 触发 Twig 模板路径事件，允许插件或主题扩展模板路径
            $this->grav->fireEvent('onTwigTemplatePaths');

            // 添加 Grav 核心模板路径
            $core_templates = array_merge($locator->findResources('system://templates'), $locator->findResources('system://templates/testing'));
            $this->twig_paths = array_merge($this->twig_paths, $core_templates);

            // 初始化文件系统加载器
            $this->loader = new FilesystemLoader($this->twig_paths);

            // 为 Twig 注册其他命名空间前缀
            foreach ($locator->getPaths('theme') as $prefix => $_) {
                if ($prefix === '') {
                    continue;
                }

                $twig_paths = [];

                // 处理语言模板（如果可用）
                if ($language->enabled()) {
                    $lang_templates = $locator->findResource('theme://'.$prefix.'templates/' . ($active_language ?: $language->getDefault()));
                    if ($lang_templates) {
                        $twig_paths[] = $lang_templates;
                    }
                }

                // 添加特定前缀的模板路径
                $twig_paths = array_merge($twig_paths, $locator->findResources('theme://'.$prefix.'templates'));

                $namespace = trim($prefix, '/');
                $this->loader->setPaths($twig_paths, $namespace);
            }

            // 触发 Twig 加载器事件，允许进一步自定义加载器
            $this->grav->fireEvent('onTwigLoader');

            // 获取系统配置中的 Twig 参数
            $params = $config->get('system.twig');
            if (!empty($params['cache'])) {
                // 设置 Twig 缓存路径
                $cachePath = $locator->findResource('cache://twig', true, true);
                $params['cache'] = new FilesystemCache($cachePath, FilesystemCache::FORCE_BYTECODE_INVALIDATION);
            }

            // 根据配置决定是否启用自动转义
            if (!$config->get('system.strict_mode.twig_compat', false)) {
                // 如果未启用严格模式，默认开启自动转义
                $params['autoescape'] = 'html';
            } elseif (!empty($this->autoescape)) {
                // 如果设置了 autoescape，则根据设置启用或禁用
                $params['autoescape'] = $this->autoescape ? 'html' : false;
            }

            // 提示开发者未来版本自动转义将被强制开启
            if (empty($params['autoescape'])) {
                user_error('erel 2.0 将强制开启 Twig 自动转义（可以通过每个模板文件单独禁用）', E_USER_DEPRECATED);
            }

            // 初始化 Twig 环境
            $this->twig = new TwigEnvironment($loader_chain, $params);

            // 注册未定义函数的回调，确保安全性
            $this->twig->registerUndefinedFunctionCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_functions');
                if (is_array($allowed) && in_array($name, $allowed, true) && function_exists($name)) {
                    return new TwigFunction($name, $name);
                }
                if ($config->get('system.twig.undefined_functions')) {
                    if (function_exists($name)) {
                        if (!Utils::isDangerousFunction($name)) {
                            user_error("PHP 函数 {$name}() 被用作 Twig 函数。这在 erel 1.7 中已弃用。请将其添加到系统配置中：`system.twig.safe_functions`", E_USER_DEPRECATED);

                            return new TwigFunction($name, $name);
                        }

                        /** @var Debugger $debugger */
                        $debugger = $this->grav['debugger'];
                        $debugger->addException(new RuntimeException("阻止潜在危险的 PHP 函数 {$name}() 被用作 Twig 函数。如果您确实需要使用它，请将其添加到系统配置中：`system.twig.safe_functions`"));
                    }

                    return new TwigFunction($name, static function () {});
                }

                return false;
            });

            // 注册未定义过滤器的回调，确保安全性
            $this->twig->registerUndefinedFilterCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_filters');
                if (is_array($allowed) && in_array($name, $allowed, true) && function_exists($name)) {
                    return new TwigFilter($name, $name);
                }
                if ($config->get('system.twig.undefined_filters')) {
                    if (function_exists($name)) {
                        if (!Utils::isDangerousFunction($name)) {
                            user_error("PHP 函数 {$name}() 被用作 Twig 过滤器。这在 erel 1.7 中已弃用。请将其添加到系统配置中：`system.twig.safe_filters`", E_USER_DEPRECATED);

                            return new TwigFilter($name, $name);
                        }

                        /** @var Debugger $debugger */
                        $debugger = $this->grav['debugger'];
                        $debugger->addException(new RuntimeException("阻止潜在危险的 PHP 函数 {$name}() 被用作 Twig 过滤器。如果您确实需要使用它，请将其添加到系统配置中：`system.twig.safe_filters`"));
                    }

                    return new TwigFilter($name, static function () {});
                }

                return false;
            });

            // 触发 Twig 初始化事件，允许插件添加自定义扩展
            $this->grav->fireEvent('onTwigInitialized');

            /** @var Pages $pages */
            $pages = $this->grav['pages'];

            // 设置 Twig 的默认变量，用于模板渲染
            $this->twig_vars += [
                    'config'            => $config,
                    'system'            => $config->get('system'),
                    'theme'             => $config->get('theme'),
                    'site'              => $config->get('site'),
                    'uri'               => $this->grav['uri'],
                    'assets'            => $this->grav['assets'],
                    'taxonomy'          => $this->grav['taxonomy'],
                    'browser'           => $this->grav['browser'],
                    'base_dir'          => GRAV_ROOT,
                    'home_url'          => $pages->homeUrl($active_language),
                    'base_url'          => $pages->baseUrl($active_language),
                    'base_url_absolute' => $pages->baseUrl($active_language, true),
                    'base_url_relative' => $pages->baseUrl($active_language, false),
                    'base_url_simple'   => $this->grav['base_url'],
                    'theme_dir'         => $locator->findResource('theme://'),
                    'theme_url'         => $this->grav['base_url'] . '/' . $locator->findResource('theme://', false),
                    'html_lang'         => $this->grav['language']->getActive() ?: $config->get('site.default_lang', 'en'),
                    'language_codes'    => new LanguageCodes,
                ];
        }
    }
        /**
         * 获取 Twig 环境实例
         *
         * @return Environment Twig 环境实例
         */
        public function twig()
        {
            return $this->twig;
        }

        /**
         * 获取文件系统加载器
         *
         * @return FilesystemLoader 文件系统加载器实例
         */
        public function loader()
        {
            return $this->loader;
        }

        /**
         * 获取 Twig 分析器配置
         *
         * @return Profile Twig 分析器配置实例
         */
        public function profile()
        {
            return $this->profile;
        }


        /**
         * 添加或覆盖一个模板
         *
         * @param string $name     模板名称
         * @param string $template 模板源代码
         */
        public function setTemplate($name, $template)
        {
            $this->loaderArray->setTemplate($name, $template);
        }

        /**
         * Twig 处理，用于渲染页面项。支持两种变体：
         * 1) 处理模块化页面，通过其模块化 Twig 模板渲染特定页面
         * 2) 在站点渲染之前，渲染单个页面项以进行 Twig 处理
         *
         * @param  PageInterface   $item    要渲染的页面项
         * @param  string|null $content 可选的内容覆盖
         *
         * @return string          渲染后的输出
         */
        public function processPage(PageInterface $item, $content = null)
        {
            $content = $content ?? $item->content();
            // 清理内容中的危险 Twig 代码
            $content = Security::cleanDangerousTwig($content);

            // 覆盖 Twig 头部变量以进行本地解析
            $this->grav->fireEvent('onTwigPageVariables', new Event(['page' => $item]));
            $twig_vars = $this->twig_vars;

            $twig_vars['page'] = $item;
            $twig_vars['media'] = $item->media();
            $twig_vars['header'] = $item->header();
            $local_twig = clone $this->twig;

            $output = '';

            try {
                if ($item->isModule()) {
                    // 如果是模块化页面，使用其特定模板渲染内容
                    $twig_vars['content'] = $content;
                    $template = $this->getPageTwigTemplate($item);
                    $output = $content = $local_twig->render($template, $twig_vars);
                }

                // 处理页面内容中的 Twig 代码
                if ($item->shouldProcess('twig')) {
                    $name = '@Page:' . $item->path();
                    $this->setTemplate($name, $content);
                    $output = $local_twig->render($name, $twig_vars);
                }

            } catch (LoaderError $e) {
                // 捕获加载错误并抛出运行时异常
                throw new RuntimeException($e->getRawMessage(), 400, $e);
            }

            return $output;
        }

        /**
         * 直接处理 Twig 模板，使用模板名称和可选的变量数组
         *
         * @param string $template 模板名称
         * @param array  $vars     可选的变量数组
         *
         * @return string 渲染后的输出
         */
        public function processTemplate($template, $vars = [])
        {
            // 覆盖 Twig 头部变量以进行本地解析
            $this->grav->fireEvent('onTwigTemplateVariables');
            $vars += $this->twig_vars;

            try {
                $output = $this->twig->render($template, $vars);
            } catch (LoaderError $e) {
                // 捕获加载错误并抛出运行时异常
                throw new RuntimeException($e->getRawMessage(), 404, $e);
            }

            return $output;
        }


        /**
         * 直接处理 Twig 字符串，使用 Twig 字符串和可选的变量数组
         *
         * @param string $string 要渲染的字符串
         * @param array  $vars   可选的变量数组
         *
         * @return string 渲染后的输出
         */
        public function processString($string, array $vars = [])
        {
            // 覆盖 Twig 头部变量以进行本地解析
            $this->grav->fireEvent('onTwigStringVariables');
            $vars += $this->twig_vars;

            // 清理字符串中的危险 Twig 代码
            $string = Security::cleanDangerousTwig($string);

            $name = '@Var:' . $string;
            $this->setTemplate($name, $string);

            try {
                $output = $this->twig->render($name, $vars);
            } catch (LoaderError $e) {
                // 捕获加载错误并抛出运行时异常
                throw new RuntimeException($e->getRawMessage(), 404, $e);
            }

            return $output;
        }

        /**
         * Twig 处理，用于渲染整个站点布局。这是主要的 Twig 处理过程，用于渲染整体页面并处理站点显示的所有布局。
         *
         * @param string|null $format 输出格式（默认为 HTML）
         * @param array $vars 可选的变量数组
         * @return string 渲染后的输出
         * @throws RuntimeException 如果渲染过程中出现错误
         */
        public function processSite($format = null, array $vars = [])
        {
            try {
                $grav = $this->grav;

                // 设置页面变量，因为页面已经被处理
                $grav->fireEvent('onTwigSiteVariables');

                /** @var Pages $pages */
                $pages = $grav['pages'];

                /** @var PageInterface $page */
                $page = $grav['page'];

                $content = Security::cleanDangerousTwig($page->content());

                $twig_vars = $this->twig_vars;
                $twig_vars['theme'] = $grav['config']->get('theme');
                $twig_vars['pages'] = $pages->root();
                $twig_vars['page'] = $page;
                $twig_vars['header'] = $page->header();
                $twig_vars['media'] = $page->media();
                $twig_vars['content'] = $content;

                // 如果设置了参数，禁用 Twig 缓存
                $params = $grav['uri']->params(null, true);
                if (!empty($params)) {
                    $this->twig->setCache(false);
                }

                // 获取 Twig 模板布局
                $template = $this->getPageTwigTemplate($page, $format);
                $page->templateFormat($format);

                // 渲染模板
                $output = $this->twig->render($template, $vars + $twig_vars);
            } catch (LoaderError $e) {
                // 捕获加载错误并抛出运行时异常
                throw new RuntimeException($e->getMessage(), 400, $e);
            } catch (RuntimeError $e) {
                $prev = $e->getPrevious();
                if ($prev instanceof TwigException) {
                    $code = $prev->getCode() ?: 500;
                    // 触发 onPageNotFound 事件
                    $event = new Event([
                        'page' => $page,
                        'code' => $code,
                        'message' => $prev->getMessage(),
                        'exception' => $prev,
                        'route' => $grav['route'],
                        'request' => $grav['request']
                    ]);
                    $event = $grav->fireEvent("onDisplayErrorPage.{$code}", $event);
                    $newPage = $event['page'];
                    if ($newPage && $newPage !== $page) {
                        // 如果有新的错误页面，更新当前页面并重新渲染
                        unset($grav['page']);
                        $grav['page'] = $newPage;

                        return $this->processSite($newPage->templateFormat(), $vars);
                    }
                }

                throw $e;
            }

            return $output;
        }

        /**
         * 包装 FilesystemLoader 的 addPath 方法（仅应在 `onTwigLoader()` 事件中使用）
         *
         * @param string $template_path 模板路径
         * @param string $namespace     命名空间
         * @throws LoaderError 如果加载器无法找到路径
         */
        public function addPath($template_path, $namespace = '__main__')
        {
            $this->loader->addPath($template_path, $namespace);
        }

        /**
         * 包装 FilesystemLoader 的 prependPath 方法（仅应在 `onTwigLoader()` 事件中使用）
         *
         * @param string $template_path 模板路径
         * @param string $namespace     命名空间
         * @throws LoaderError 如果加载器无法找到路径
         */
        public function prependPath($template_path, $namespace = '__main__')
        {
            $this->loader->prependPath($template_path, $namespace);
        }

        /**
         * 简单的辅助方法，用于获取已设置的模板，如果未设置，则返回传入的模板
         * 注意：被注入的模块化页面不应使用预设的模板，因为它通常在页面级别设置
         *
         * @param  string $template 要获取的模板名称
         * @return string           最终使用的模板名称
         */
        public function template(string $template): string
        {
            if (isset($this->template)) {
                $template = $this->template;
                unset($this->template);
            }
            
            return $template;
        }

        /**
         * 获取页面的 Twig 模板。如果模板已设置，则使用该模板，否则根据格式和默认模板进行选择
         *
         * @param PageInterface $page   页面对象
         * @param string|null $format 输出格式
         * @return string               使用的模板名称
         */
        public function getPageTwigTemplate($page, &$format = null)
        {
            $template = $page->template();
            $default = $page->isModule() ? 'modular/default' : 'default';
            $extension = $format ?: $page->templateFormat();
            $twig_extension = $extension ? '.' . $extension . TWIG_EXT : TEMPLATE_EXT;
            $template_file = $this->template($template . $twig_extension);

            // 检查模板是否存在，优先选择更具体的模板
            /** @var ExistsLoaderInterface $loader */
            $loader = $this->twig->getLoader();
            if ($loader->exists($template_file)) {
                // 使用指定的模板文件
                $page_template = $template_file;
            } elseif ($twig_extension !== TEMPLATE_EXT && $loader->exists($template . TEMPLATE_EXT)) {
                // 如果带有特定扩展名的模板不存在，尝试默认扩展名
                $page_template = $template . TEMPLATE_EXT;
                $format = 'html';
            } elseif ($loader->exists($default . $twig_extension)) {
                // 使用默认模板文件
                $page_template = $default . $twig_extension;
            } else {
                // 回退到默认的 HTML 模板
                $page_template = $default . TEMPLATE_EXT;
                $format = 'html';
            }

            return $page_template;

        }

        /**
         * 覆盖自动转义设置
         *
         * @param bool $state 是否启用自动转义
         * @return void
         * @deprecated 1.5 自动转义应始终开启以防止 XSS 问题（可以在每个模板文件中单独禁用）
         */
        public function setAutoescape($state)
        {
            if (!$state) {
                user_error(__CLASS__ . '::' . __FUNCTION__ . '(false) 自 Grav 1.5 起已弃用', E_USER_DEPRECATED);
            }

            $this->autoescape = (bool) $state;
        }

    }
