<?php
namespace Grav\Common;

use Composer\Autoload\ClassLoader;
use Grav\Common\Config\Config;
use Grav\Common\Config\Setup;
use Grav\Common\Helpers\Exif;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Pages;
use Grav\Common\Processors\AssetsProcessor;
use Grav\Common\Processors\BackupsProcessor;
use Grav\Common\Processors\DebuggerAssetsProcessor;
use Grav\Common\Processors\InitializeProcessor;
use Grav\Common\Processors\PagesProcessor;
use Grav\Common\Processors\PluginsProcessor;
use Grav\Common\Processors\RenderProcessor;
use Grav\Common\Processors\RequestProcessor;
use Grav\Common\Processors\SchedulerProcessor;
use Grav\Common\Processors\TasksProcessor;
use Grav\Common\Processors\ThemesProcessor;
use Grav\Common\Processors\TwigProcessor;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Service\AccountsServiceProvider;
use Grav\Common\Service\AssetsServiceProvider;
use Grav\Common\Service\BackupsServiceProvider;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Service\ErrorServiceProvider;
use Grav\Common\Service\FilesystemServiceProvider;
use Grav\Common\Service\FlexServiceProvider;
use Grav\Common\Service\InflectorServiceProvider;
use Grav\Common\Service\LoggerServiceProvider;
use Grav\Common\Service\OutputServiceProvider;
use Grav\Common\Service\PagesServiceProvider;
use Grav\Common\Service\RequestServiceProvider;
use Grav\Common\Service\SessionServiceProvider;
use Grav\Common\Service\StreamsServiceProvider;
use Grav\Common\Service\TaskServiceProvider;
use Grav\Common\Twig\Twig;
use Grav\Framework\DI\Container;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\Middlewares\MultipartRequestSupport;
use Grav\Framework\RequestHandler\RequestHandler;
use Grav\Framework\Route\Route;
use Grav\Framework\Session\Messages;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function array_key_exists;
use function call_user_func_array;
use function function_exists;
use function get_class;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function strlen;

/**
 * Grav.php 容器是 erelcms 的核心。
 *
 * @package Grav\Common
 */
class Grav extends Container
{
    /** @var string 处理后的页面输出。 */
    public $output;

    /** @var static 单例实例 */
    protected static $instance;

    /**
     * @var array 包含所有服务和服务提供者，这些服务和服务提供者被映射到依赖注入容器中。
     *            可以是服务提供者的类名，或者是 serviceKey => serviceClass 的键值对。
     */
    protected static $diMap = [
        AccountsServiceProvider::class,
        AssetsServiceProvider::class,
        BackupsServiceProvider::class,
        ConfigServiceProvider::class,
        ErrorServiceProvider::class,
        FilesystemServiceProvider::class,
        FlexServiceProvider::class,
        InflectorServiceProvider::class,
        LoggerServiceProvider::class,
        OutputServiceProvider::class,
        PagesServiceProvider::class,
        RequestServiceProvider::class,
        SessionServiceProvider::class,
        StreamsServiceProvider::class,
        TaskServiceProvider::class,
        'browser'    => Browser::class,
        'cache'      => Cache::class,
        'events'     => EventDispatcher::class,
        'exif'       => Exif::class,
        'plugins'    => Plugins::class,
        'scheduler'  => Scheduler::class,
        'taxonomy'   => Taxonomy::class,
        'themes'     => Themes::class,
        'twig'       => Twig::class,
        'uri'        => Uri::class,
    ];

    /**
     * @var array 所有中间件处理器，这些处理器将在 $this->process() 中被处理。
     */
    protected $middleware = [
        'multipartRequestSupport',
        'initializeProcessor',
        'pluginsProcessor',
        'themesProcessor',
        'requestProcessor',
        'tasksProcessor',
        'backupsProcessor',
        'schedulerProcessor',
        'assetsProcessor',
        'twigProcessor',
        'pagesProcessor',
        'debuggerAssetsProcessor',
        'renderProcessor',
    ];

    /** @var array */
    protected $initialized = [];

    /**
     * 重置 erel 实例。
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        if (self::$instance) {
            // @phpstan-ignore-next-line
            self::$instance = null;
        }
    }

    /**
     * 返回 erel 实例。如果尚未实例化，则创建一个新的实例。
     *
     * @param array $values
     * @return erel
     */
    public static function instance(array $values = [])
    {
        if (null === self::$instance) {
            self::$instance = static::load($values);

            /** @var ClassLoader|null $loader */
            $loader = self::$instance['loader'] ?? null;
            if ($loader) {
                // 加载 Deferred Twig 扩展的修复
                $loader->addPsr4('Phive\\Twig\\Extensions\\Deferred\\', LIB_DIR . 'Phive/Twig/Extensions/Deferred/', true);
            }
        } elseif ($values) {
            $instance = self::$instance;
            foreach ($values as $key => $value) {
                $instance->offsetSet($key, $value);
            }
        }

        return self::$instance;
    }

    /**
     * 获取 erel 版本。
     *
     * @return string
     */
    public function getVersion(): string
    {
        return GRAV_VERSION;
    }

    /**
     * 检查是否已经完成设置。
     *
     * @return bool
     */
    public function isSetup(): bool
    {
        return isset($this->initialized['setup']);
    }

    /**
     * 使用特定环境设置 erel 实例。
     *
     * @param string|null $environment
     * @return $this
     */
    public function setup(string $environment = null)
    {
        if (isset($this->initialized['setup'])) {
            return $this;
        }

        $this->initialized['setup'] = true;

        // 如果传递了环境参数，强制使用该环境。
        if ($environment) {
            Setup::$environment = $environment;
        }

        // 初始化 setup 和 streams。
        $this['setup'];
        $this['streams'];

        return $this;
    }

    /**
     * 初始化 CLI 环境。
     *
     * 在 `$grav->setup($environment)` 之后调用。
     *
     * - 加载配置
     * - 初始化日志记录器
     * - 禁用调试器
     * - 设置时区和区域设置
     * - 加载插件（调用 PluginsLoadedEvent）
     * - 设置站点使用的页面和用户类型
     *
     * 此方法不会初始化资产、Twig 或页面。
     *
     * @return $this
     */
    public function initializeCli()
    {
        InitializeProcessor::initializeCli($this);

        return $this;
    }

    /**
     * 处理请求。
     *
     * @return void
     */
    public function process(): void
    {
        if (isset($this->initialized['process'])) {
            return;
        }

        // 如果需要，初始化 erel。
        $this->setup();

        $this->initialized['process'] = true;

        $container = new Container(
            [
                'multipartRequestSupport' => function () {
                    return new MultipartRequestSupport();
                },
                'initializeProcessor' => function () {
                    return new InitializeProcessor($this);
                },
                'backupsProcessor' => function () {
                    return new BackupsProcessor($this);
                },
                'pluginsProcessor' => function () {
                    return new PluginsProcessor($this);
                },
                'themesProcessor' => function () {
                    return new ThemesProcessor($this);
                },
                'schedulerProcessor' => function () {
                    return new SchedulerProcessor($this);
                },
                'requestProcessor' => function () {
                    return new RequestProcessor($this);
                },
                'tasksProcessor' => function () {
                    return new TasksProcessor($this);
                },
                'assetsProcessor' => function () {
                    return new AssetsProcessor($this);
                },
                'twigProcessor' => function () {
                    return new TwigProcessor($this);
                },
                'pagesProcessor' => function () {
                    return new PagesProcessor($this);
                },
                'debuggerAssetsProcessor' => function () {
                    return new DebuggerAssetsProcessor($this);
                },
                'renderProcessor' => function () {
                    return new RenderProcessor($this);
                },
            ]
        );

        $default = static function () {
            return new Response(404, ['Expires' => 0, 'Cache-Control' => 'no-store, max-age=0'], 'Not Found');
        };

        $collection = new RequestHandler($this->middleware, $default, $container);

        $response = $collection->handle($this['request']);
        $body = $response->getBody();

        /** @var Messages $messages */
        $messages = $this['messages'];

        // 如果会话消息在页面中显示，则防止缓存。
        $noCache = $messages->isCleared();
        if ($noCache) {
            $response = $response->withHeader('Cache-Control', 'no-store, max-age=0');
        }

        // 处理 ETag 和 If-None-Match 头。
        if ($response->getHeaderLine('ETag') === '1') {
            $etag = md5($body);
            $response = $response->withHeader('ETag', '"' . $etag . '"');

            $search = trim($this['request']->getHeaderLine('If-None-Match'), '"');
            if ($noCache === false && $search === $etag) {
                $response = $response->withStatus(304);
                $body = '';
            }
        }

        // 输出页面内容。
        $this->header($response);
        echo $body;

        $this['debugger']->render();

        // 响应对象可以关闭所有关闭处理。这可以用于例如加快 AJAX 响应。
        // 请注意，使用此功能也会关闭响应压缩。
        if ($response->getHeaderLine('Grav-Internal-SkipShutdown') !== '1') {
            register_shutdown_function([$this, 'shutdown']);
        }
    }

    /**
     * 清理任何输出缓冲区。当从应用程序退出时非常有用。
     *
     * 请使用 `$grav->close()` 和 `$grav->redirect()` 而不是直接调用此方法！
     *
     * @return void
     */
    public function cleanOutputBuffers(): void
    {
        // 确保没有额外的内容被写入响应。
        while (ob_get_level()) {
            ob_end_clean();
        }
        // 解决 PHP bug #8218 (8.0.17 & 8.1.4)。
        header_remove('Content-Encoding');
    }

    /**
     * 使用响应终止 erel 请求。
     *
     * 请使用此方法而不是调用 `die();` 或 `exit();`。注意，您需要创建一个响应对象。
     *
     * @param ResponseInterface $response
     * @return never-return
     */
    public function close(ResponseInterface $response): void
    {
        $this->cleanOutputBuffers();

        // 关闭会话。
        if (isset($this['session'])) {
            $this['session']->close();
        }

        /** @var ServerRequestInterface $request */
        $request = $this['request'];

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $response = $debugger->logRequest($request, $response);

        $body = $response->getBody();

        /** @var Messages $messages */
        $messages = $this['messages'];

        // 如果会话消息在页面中显示，则防止缓存。
        $noCache = $messages->isCleared();
        if ($noCache) {
            $response = $response->withHeader('Cache-Control', 'no-store, max-age=0');
        }

        // 处理 ETag 和 If-None-Match 头。
        if ($response->getHeaderLine('ETag') === '1') {
            $etag = md5($body);
            $response = $response->withHeader('ETag', '"' . $etag . '"');

            $search = trim($this['request']->getHeaderLine('If-None-Match'), '"');
            if ($noCache === false && $search === $etag) {
                $response = $response->withStatus(304);
                $body = '';
            }
        }

        // 输出页面内容。
        $this->header($response);
        echo $body;
        exit();
    }

    /**
     * @param ResponseInterface $response
     * @return never-return
     * @deprecated 1.7 使用 `$grav->close()` 代替。
     */
    public function exit(ResponseInterface $response): void
    {
        $this->close($response);
    }

    /**
     * 终止 erel 请求并将浏览器重定向到另一个位置。
     *
     * 请使用此方法而不是调用 `header("Location: {$url}", true, 302); exit();`。
     *
     * @param Route|string $route 内部路由。
     * @param int|null $code  重定向代码 (30x)
     * @return never-return
     */
    public function redirect($route, $code = null): void
    {
        $response = $this->getRedirectResponse($route, $code);

        $this->close($response);
    }

    /**
     * 从 erel 返回重定向响应对象。
     *
     * @param Route|string $route 内部路由。
     * @param int|null $code  重定向代码 (30x)
     * @return ResponseInterface
     */
    public function getRedirectResponse($route, $code = null): ResponseInterface
    {
        /** @var Uri $uri */
        $uri = $this['uri'];

        if (is_string($route)) {
            // 清理重定向路由
            $route = preg_replace("#^\/[\\\/]+\/#", '/', $route);

            if (null === $code) {
                // 检查路由中的重定向代码，例如 /new/[301]，/new[301]/route 或 /new[301].html
                $regex = '/.*(\[(30[1-7])\])(.\w+|\/.*?)?$/';
                preg_match($regex, $route, $matches);
                if ($matches) {
                    $route = str_replace($matches[1], '', $matches[0]);
                    $code = $matches[2];
                }
            }

            if ($uri::isExternal($route)) {
                $url = $route;
            } else {
                $url = rtrim($uri->rootUrl(), '/') . '/';

                if ($this['config']->get('system.pages.redirect_trailing_slash', true)) {
                    $url .= trim($route, '/'); // 移除尾部斜杠
                } else {
                    $url .= ltrim($route, '/'); // 支持尾部斜杠的默认路由
                }
            }
        } elseif ($route instanceof Route) {
            $url = $route->toString(true);
        } else {
            throw new InvalidArgumentException('无效的 $route 参数');
        }

        if ($code < 300 || $code > 399) {
            $code = null;
        }

        if ($code === null) {
            $code = $this['config']->get('system.pages.redirect_default_code', 302);
        }

        if ($uri->extension() === 'json') {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['code' => $code, 'redirect' => $url], JSON_THROW_ON_ERROR));
        }

        return new Response($code, ['Location' => $url]);
    }

    /**
     * 根据语言安全地重定向浏览器到另一个位置（首选）。
     *
     * @param string $route 内部路由。
     * @param int    $code  重定向代码 (30x)
     * @return void
     */
    public function redirectLangSafe($route, $code = null): void
    {
        if (!$this['uri']->isExternal($route)) {
            $this->redirect($this['pages']->route($route), $code);
        } else {
            $this->redirect($route, $code);
        }
    }

    /**
     * 设置响应头。
     *
     * @param ResponseInterface|null $response
     * @return void
     */
    public function header(ResponseInterface $response = null): void
    {
        if (null === $response) {
            /** @var PageInterface $page */
            $page = $this['page'];
            $response = new Response($page->httpResponseCode(), $page->httpHeaders(), '');
        }

        header("HTTP/{$response->getProtocolVersion()} {$response->getStatusCode()} {$response->getReasonPhrase()}");
        foreach ($response->getHeaders() as $key => $values) {
            // 跳过内部 erel 头。
            if (strpos($key, 'Grav-Internal-') === 0) {
                continue;
            }
            foreach ($values as $i => $value) {
                header($key . ': ' . $value, $i === 0);
            }
        }
    }

    /**
     * 根据语言和配置设置系统区域设置。
     *
     * @return void
     */
    public function setLocale(): void
    {
        // 如果启用了语言并且配置了覆盖区域设置，则初始化区域设置。
        if ($this['language']->enabled() && $this['config']->get('system.languages.override_locale')) {
            $language = $this['language']->getLanguage();
            setlocale(LC_ALL, strlen($language) < 3 ? ($language . '_' . strtoupper($language)) : $language);
        } elseif ($this['config']->get('system.default_locale')) {
            setlocale(LC_ALL, $this['config']->get('system.default_locale'));
        }
    }

    /**
     * 分发事件。
     *
     * @param object $event
     * @return object
     */
    public function dispatchEvent($event)
    {
        /** @var EventDispatcherInterface $events */
        $events = $this['events'];
        $eventName = get_class($event);

        $timestamp = microtime(true);
        $event = $events->dispatch($event);

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $debugger->addEvent($eventName, $event, $events, $timestamp);

        return $event;
    }

    /**
     * 触发一个带有可选参数的事件。
     *
     * @param  string $eventName 事件名称
     * @param  Event|null $event 事件对象
     * @return Event
     */
    public function fireEvent($eventName, Event $event = null)
    {
        /** @var EventDispatcherInterface $events */
        $events = $this['events'];
        if (null === $event) {
            $event = new Event();
        }

        $timestamp = microtime(true);
        $events->dispatch($event, $eventName);

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $debugger->addEvent($eventName, $event, $events, $timestamp);

        return $event;
    }

    /**
     * 设置页面的最终内容长度并刷新缓冲区。
     *
     * @return void
     */
    public function shutdown(): void
    {
        // 防止用户中断，允许 onShutdown 事件无中断地运行。
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // 关闭会话，允许处理新的请求。
        if (isset($this['session'])) {
            $this['session']->close();
        }

        /** @var Config $config */
        $config = $this['config'];
        if ($config->get('system.debugger.shutdown.close_connection', true)) {
            // 刷新响应并关闭连接，以允许执行耗时任务而不保持客户端连接。
            // 这将使页面加载感觉更快。

            // FastCGI 允许我们将所有响应数据刷新到客户端并完成请求。
            $success = function_exists('fastcgi_finish_request') ? @fastcgi_finish_request() : false;
            if (!$success) {
                // 不幸的是，没有 FastCGI 无法强制关闭连接。
                // 我们需要请求浏览器为我们关闭连接。

                if ($config->get('system.cache.gzip')) {
                    // 如果启用了 gzip 设置，刷新 gzhandler 缓冲区以获取压缩输出的大小。
                    ob_end_flush();
                } elseif ($config->get('system.cache.allow_webserver_gzip')) {
                    // 让 Web 服务器完成繁重的工作。
                    header('Content-Encoding: identity');
                } elseif (function_exists('apache_setenv')) {
                    // 没有 gzip，我们别无选择，只能防止服务器压缩输出。
                    // 此操作关闭 mod_deflate，这将防止我们关闭连接。
                    @apache_setenv('no-gzip', '1');
                } else {
                    // 回退到未知内容编码，它可以防止大多数服务器解压缩内容。
                    header('Content-Encoding: none');
                }

                // 获取长度并关闭连接。
                header('Content-Length: ' . ob_get_length());
                header('Connection: close');

                ob_end_flush();
                @ob_flush();
                flush();
            }
        }

        // 运行任何耗时任务。
        $this->fireEvent('onShutdown');
    }

    /**
     * 魔术捕获所有函数。
     *
     * 用于调用闭包。
     *
     * @param string $method 方法名
     * @param array $args 参数数组
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function __call($method, $args)
    {
        $closure = $this->{$method} ?? null;

        return is_callable($closure) ? $closure(...$args) : null;
    }

    /**
     * 测量执行一个操作所需的时间。
     *
     * @param string $timerId    计时器标识
     * @param string $timerTitle 计时器标题
     * @param callable $callback 执行的回调函数
     * @return mixed              返回回调函数的结果。
     */
    public function measureTime(string $timerId, string $timerTitle, callable $callback)
    {
        $debugger = $this['debugger'];
        $debugger->startTimer($timerId, $timerTitle);
        $result = $callback();
        $debugger->stopTimer($timerId);

        return $result;
    }

    /**
     * 初始化并返回一个 erel 实例。
     *
     * @param  array $values 初始化时传入的值
     * @return static
     */
    protected static function load(array $values)
    {
        $container = new static($values);

        $container['debugger'] = new Debugger();
        $container['grav'] = function (Container $container) {
            user_error('调用 $grav[\'grav\'] 或 {{ grav.grav }} 已在 erel 1.6 中弃用.', E_USER_DEPRECATED);

            return $container;
        };

        $container->registerServices();

        return $container;
    }

    /**
     * 注册所有服务。
     * 服务在 diMap 中定义。它们可以是服务提供者的类，或者是 serviceKey => serviceClass 的键值对，
     * 这些键值对将直接映射到容器中。
     *
     * @return void
     */
    protected function registerServices(): void
    {
        foreach (self::$diMap as $serviceKey => $serviceClass) {
            if (is_int($serviceKey)) {
                $this->register(new $serviceClass);
            } else {
                $this[$serviceKey] = function ($c) use ($serviceClass) {
                    return new $serviceClass($c);
                };
            }
        }
    }

    /**
     * 尝试查找媒体、其他文件，并下载它们。
     *
     * @param string $path 文件路径
     * @return PageInterface|false 返回页面接口或 false 如果未找到
     */
    public function fallbackUrl($path)
    {
        $path_parts = Utils::pathinfo($path);
        if (!is_array($path_parts)) {
            return false;
        }

        /** @var Uri $uri */
        $uri = $this['uri'];

        /** @var Config $config */
        $config = $this['config'];

        /** @var Pages $pages */
        $pages = $this['pages'];
        $page = $pages->find($path_parts['dirname'], true);

        $uri_extension = strtolower($uri->extension() ?? '');
        $fallback_types = $config->get('system.media.allowed_fallback_types');
        $supported_types = $config->get('media.types');

        $parsed_url = parse_url(rawurldecode($uri->basename()));
        $media_file = $parsed_url['path'];

        $event = new Event([
            'uri' => $uri,
            'page' => &$page,
            'filename' => &$media_file,
            'extension' => $uri_extension,
            'allowed_fallback_types' => &$fallback_types,
            'media_types' => &$supported_types
        ]);

        $this->fireEvent('onPageFallBackUrl', $event);

        // 先检查白名单，然后确保扩展名是有效的媒体类型
        if (!empty($fallback_types) && !in_array($uri_extension, $fallback_types, true)) {
            return false;
        }
        if (!array_key_exists($uri_extension, $supported_types)) {
            return false;
        }

        if ($page) {
            $media = $page->media()->all();

            // 如果这是一个媒体对象，先尝试执行操作
            if (isset($media[$media_file])) {
                /** @var Medium $medium */
                $medium = $media[$media_file];
                foreach ($uri->query(null, true) as $action => $params) {
                    if (in_array($action, ImageMedium::$magic_actions, true)) {
                        call_user_func_array([&$medium, $action], explode(',', $params));
                    }
                }
                Utils::download($medium->path(), false);
            }

            // 不支持的媒体类型，尝试下载它...
            if ($uri_extension) {
                $extension = $uri_extension;
            } elseif (isset($path_parts['extension'])) {
                $extension = $path_parts['extension'];
            } else {
                $extension = null;
            }

            if ($extension) {
                $download = true;
                if (in_array(ltrim($extension, '.'), $config->get('system.media.unsupported_inline_types', []), true)) {
                    $download = false;
                }
                Utils::download($page->path() . DIRECTORY_SEPARATOR . $uri->basename(), $download);
            }
        }

        // 未找到任何内容
        return false;
    }
}
