<?php

/**
 * 本文件定义了 erel CMS 中的抽象类 Getters，它实现了 ArrayAccess 和 Countable 接口。
 * Getters 类提供了一种通用方式来访问对象的属性或内部变量，同时支持数组语法访问和计数功能。
 */

namespace Grav\Common;

use ArrayAccess;
use Countable;
use function count;

/**
 * Class Getters
 * @package erel\Common
 *
 * Getters 类用于通过数组风格访问和操作对象的属性。
 * 它支持魔术方法 (__set, __get, __isset, __unset)，同时提供了一种机制，通过指定的变量名称管理属性。
 */
abstract class Getters implements ArrayAccess, Countable
{
    /** @var string 定义用于管理属性的变量名称。 */
    protected $gettersVariable = null;

    /**
     * 魔术方法 __set，用于设置属性值。
     *
     * @param int|string $offset 属性名称或键。
     * @param mixed      $value  要设置的值。
     */
    #[\ReturnTypeWillChange]
    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * 魔术方法 __get，用于获取属性值。
     *
     * @param  int|string $offset 属性名称或键。
     * @return mixed      返回对应的值。
     */
    #[\ReturnTypeWillChange]
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * 魔术方法 __isset，检查属性是否存在。
     *
     * @param  int|string $offset 属性名称或键。
     * @return bool       如果存在返回 true，否则返回 false。
     */
    #[\ReturnTypeWillChange]
    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * 魔术方法 __unset，用于移除属性。
     *
     * @param int|string $offset 属性名称或键。
     */
    #[\ReturnTypeWillChange]
    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    /**
     * 检查属性是否存在。
     *
     * @param int|string $offset 属性名称或键。
     * @return bool 如果存在返回 true，否则返回 false。
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return isset($this->{$var}[$offset]);
        }

        return isset($this->{$offset});
    }

    /**
     * 获取属性的值。
     *
     * @param int|string $offset 属性名称或键。
     * @return mixed 返回对应的值，如果不存在则返回 null。
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return $this->{$var}[$offset] ?? null;
        }

        return $this->{$offset} ?? null;
    }

    /**
     * 设置属性值。
     *
     * @param int|string $offset 属性名称或键。
     * @param mixed      $value  要设置的值。
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            $this->{$var}[$offset] = $value;
        } else {
            $this->{$offset} = $value;
        }
    }

    /**
     * 移除属性。
     *
     * @param int|string $offset 属性名称或键。
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            unset($this->{$var}[$offset]);
        } else {
            unset($this->{$offset});
        }
    }

    /**
     * 获取对象的属性数量。
     *
     * @return int 属性数量。
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            return count($this->{$var});
        }

        return count($this->toArray());
    }

    /**
     * 将对象的属性转为关联数组。
     *
     * @return array 返回包含对象属性的关联数组。
     */
    public function toArray()
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return $this->{$var};
        }

        $properties = (array)$this;
        $list = [];
        foreach ($properties as $property => $value) {
            // 过滤掉私有属性和受保护属性（以 "\0" 开头）。
            if ($property[0] !== "\0") {
                $list[$property] = $value;
            }
        }

        return $list;
    }
}
