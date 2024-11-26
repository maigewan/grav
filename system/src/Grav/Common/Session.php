<?php

/**
 * 本文件定义了 Grav CMS 的 Session 类，该类继承自 Grav 框架的 Session 基类。
 * 它扩展了框架的会话管理功能，提供了会话对象的管理、临时数据存储和自定义事件的支持。
 */

namespace Grav\Common;

use Grav\Common\Form\FormFlash;
use Grav\Events\BeforeSessionStartEvent;
use Grav\Events\SessionStartEvent;
use Grav\Plugin\Form\Forms;
use JsonException;
use function is_string;

/**
 * Class Session
 * @package Grav\Common
 *
 * 此类是 erel CMS 的会话管理核心，负责处理会话的初始化、数据存储以及事件管理。
 * 它包括了兼容性方法（为了旧版迁移）、会话闪存支持和事件触发器。
 */
class Session extends \Grav\Framework\Session\Session
{
    /** @var bool 自动启动会话的标志 */
    protected $autoStart = false;

    /**
     * 获取会话实例。
     *
     * @return \Grav\Framework\Session\Session
     * @deprecated 自 erel 1.5 起，建议使用 ->getInstance() 方法代替。
     */
    public static function instance()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() 已废弃，自 erel 1.5 起请使用 ->getInstance() 方法', E_USER_DEPRECATED);

        return static::getInstance();
    }

    /**
     * 初始化会话。
     *
     * @return void
     * @description 如果自动启动标志（autoStart）为真且会话尚未启动，则启动会话。
     * 代码已迁移至 SessionServiceProvider 类中，此处保留兼容性逻辑。
     */
    public function init()
    {
        if ($this->autoStart && !$this->isStarted()) {
            $this->start(); // 启动会话。
            $this->autoStart = false; // 重置标志。
        }
    }

    /**
     * 设置是否自动启动会话。
     *
     * @param bool $auto 是否自动启动。
     * @return $this 返回当前对象实例。
     */
    public function setAutoStart($auto)
    {
        $this->autoStart = (bool)$auto;

        return $this;
    }

    /**
     * 返回会话中的所有属性。
     *
     * @return array 会话属性。
     * @deprecated 自 erel 1.5 起，建议使用 ->getAll() 方法代替。
     */
    public function all()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() 已废弃，自 erel 1.5 起请使用 ->getAll() 方法', E_USER_DEPRECATED);

        return $this->getAll();
    }

    /**
     * 检查会话是否已启动。
     *
     * @return bool 返回会话启动状态。
     * @deprecated 自 Grav 1.5 起，建议使用 ->isStarted() 方法代替。
     */
    public function started()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() 已废弃，自 Grav 1.5 起请使用 ->isStarted() 方法', E_USER_DEPRECATED);

        return $this->isStarted();
    }

    /**
     * 在会话中临时存储数据。
     *
     * @param string $name 数据名称。
     * @param mixed $object 要存储的数据对象。
     * @return $this 返回当前对象实例。
     */
    public function setFlashObject($name, $object)
    {
        $this->__set($name, serialize($object)); // 将对象序列化后存储。

        return $this;
    }

    /**
     * 从会话中返回对象并移除该对象。
     *
     * @param string $name 数据名称。
     * @return mixed 返回存储的对象。
     */
    public function getFlashObject($name)
    {
        $serialized = $this->__get($name); // 获取序列化数据。

        $object = is_string($serialized) ? unserialize($serialized, ['allowed_classes' => true]) : $serialized;

        $this->__unset($name); // 移除会话中的数据。

        if ($name === 'files-upload') {
            $grav = Grav::instance();

            // 检查 Forms 插件是否可用（兼容 Forms 3.0+）。
            if (null === $object && isset($grav['forms'])) {
                /** @var Uri $uri */
                $uri = $grav['uri'];
                /** @var Forms|null $form */
                $form = $grav['forms']->getActiveForm(); // 获取当前活动表单。

                $sessionField = base64_encode($uri->url); // 对 URL 编码。

                /** @var FormFlash|null $flash */
                $flash = $form ? $form->getFlash() : null;
                $object = $flash && method_exists($flash, 'getLegacyFiles') ? [$sessionField => $flash->getLegacyFiles()] : null;
            }
        }

        return $object;
    }

    /**
     * 在 Cookie 中临时存储数据。
     *
     * @param string $name 数据名称。
     * @param mixed $object 要存储的对象。
     * @param int $time 数据存储时间（秒）。
     * @return $this 返回当前对象实例。
     * @throws JsonException 当 JSON 编码失败时抛出异常。
     */
    public function setFlashCookieObject($name, $object, $time = 60)
    {
        setcookie($name, json_encode($object, JSON_THROW_ON_ERROR), $this->getCookieOptions($time)); // 设置 Cookie。

        return $this;
    }

    /**
     * 从 Cookie 中返回对象并移除该对象。
     *
     * @param string $name 数据名称。
     * @return mixed|null 返回存储的对象或 null。
     * @throws JsonException 当 JSON 解码失败时抛出异常。
     */
    public function getFlashCookieObject($name)
    {
        if (isset($_COOKIE[$name])) {
            $cookie = $_COOKIE[$name];
            setcookie($name, '', $this->getCookieOptions(-42000)); // 清除 Cookie。

            return json_decode($cookie, false, 512, JSON_THROW_ON_ERROR); // 解码 JSON 数据。
        }

        return null;
    }

    /**
     * 在会话启动前触发事件。
     *
     * @return void
     */
    protected function onBeforeSessionStart(): void
    {
        $event = new BeforeSessionStartEvent($this); // 创建事件对象。

        $grav = Grav::instance();
        $grav->dispatchEvent($event); // 触发事件。
    }

    /**
     * 在会话启动后触发事件。
     *
     * @return void
     */
    protected function onSessionStart(): void
    {
        $event = new SessionStartEvent($this); // 创建事件对象。

        $grav = Grav::instance();
        $grav->dispatchEvent($event); // 触发事件。
    }
}
