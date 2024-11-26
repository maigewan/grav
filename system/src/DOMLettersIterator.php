<?php

/**
 * 遍历DOM文本和CDATA节点的每个字符（Unicode代码点），
 * 同时跟踪它们在文档中的位置。
 *
 * 示例：
 *
 *  $doc = new DOMDocument();
 *  $doc->load('example.xml');
 *  foreach(new DOMLettersIterator($doc) as $letter) echo $letter;
 *
 * 注意：
 * 如果只需要获取文本内容而不需要它们在文档中的位置信息，
 * 可以直接使用DOMNode->textContent。
 *
 * 作者：porneL http://pornel.net
 * 授权协议：公共领域
 * 原始代码地址：https://github.com/antoligy/dom-string-iterators
 *
 * 实现Iterator接口，允许以迭代方式操作字符串。
 *
 * @implements Iterator<int,string>
 */
final class DOMLettersIterator implements Iterator
{
    /** @var DOMElement 起始元素 */
    private $start;
    /** @var DOMElement|null 当前节点，可能为null表示遍历结束 */
    private $current;
    /** @var int 当前偏移量，初始值为-1 */
    private $offset = -1;
    /** @var int|null 当前的迭代键值 */
    private $key;
    /** @var array<int,string>|null 当前节点中提取的字符数组 */
    private $letters;

    /**
     * 构造函数，接收DOMElement或DOMDocument对象。
     * 如果传入的是DOMDocument对象，会提取其documentElement作为起始节点。
     *
     * @param DOMNode $el DOM元素或文档对象
     * @throws InvalidArgumentException 如果传入的参数不是DOMElement或DOMDocument
     */
    public function __construct(DOMNode $el)
    {
        if ($el instanceof DOMDocument) {
            $el = $el->documentElement;
        }

        if (!$el instanceof DOMElement) {
            throw new InvalidArgumentException('参数无效，期望类型为DOMElement或DOMDocument');
        }

        $this->start = $el;
    }

    /**
     * 返回文本中的当前位置，包括DOMText节点和字符偏移量。
     * 偏移量不是字节偏移量，如果要使用这个偏移量操作字符串，
     * 需要使用如mb_substr()等支持多字节操作的函数。
     * 如果迭代器结束，节点可能为NULL。
     *
     * @return array 包含两个元素：[DOMText节点或null, 当前偏移量]
     */
    public function currentTextPosition(): array
    {
        return [$this->current, $this->offset];
    }

    /**
     * 返回当前正在遍历的DOMElement或NULL（当迭代器结束时）。
     * 这是通过获取当前节点的父节点实现的。
     *
     * @return DOMElement|null
     */
    public function currentElement(): ?DOMElement
    {
        return $this->current ? $this->current->parentNode : null;
    }

    // 实现Iterator接口的方法

    /**
     * 返回当前的键值（位置）。
     * 这是迭代器的唯一标识，用于跟踪当前位置。
     *
     * @return int|null
     */
    public function key(): ?int
    {
        return $this->key;
    }

    /**
     * 移动到下一个字符。
     * 包括处理文本节点、CDATA段以及遍历DOM树的逻辑。
     *
     * @return void
     */
    public function next(): void
    {
        if (null === $this->current) {
            return;
        }

        // 如果当前是文本或CDATA节点
        if ($this->current->nodeType === XML_TEXT_NODE || $this->current->nodeType === XML_CDATA_SECTION_NODE) {
            // 第一次进入节点时，提取所有字符
            if ($this->offset === -1) {
                preg_match_all('/./us', $this->current->textContent, $m);
                $this->letters = $m[0];
            }

            $this->offset++;
            $this->key++;
            // 如果还有剩余字符，继续迭代
            if ($this->letters && $this->offset < count($this->letters)) {
                return;
            }

            $this->offset = -1; // 当前节点处理完毕
        }

        // 遍历子节点
        while ($this->current->nodeType === XML_ELEMENT_NODE && $this->current->firstChild) {
            $this->current = $this->current->firstChild;
            if ($this->current->nodeType === XML_TEXT_NODE || $this->current->nodeType === XML_CDATA_SECTION_NODE) {
                $this->next();
                return;
            }
        }

        // 如果没有兄弟节点，回到父节点
        while (!$this->current->nextSibling && $this->current->parentNode) {
            $this->current = $this->current->parentNode;
            if ($this->current === $this->start) {
                $this->current = null; // 遍历结束
                return;
            }
        }

        // 移动到下一个兄弟节点
        $this->current = $this->current->nextSibling;

        $this->next(); // 递归调用处理下一个节点
    }

    /**
     * 返回当前字符。
     * 如果当前节点为空或已经遍历完，则返回NULL。
     *
     * @link https://php.net/manual/en/iterator.current.php
     * @return string|null
     */
    public function current(): ?string
    {
        return $this->letters ? $this->letters[$this->offset] : null;
    }

    /**
     * 检查当前迭代位置是否有效。
     * 如果current()返回一个有效的节点，则此方法返回true。
     *
     * @link https://php.net/manual/en/iterator.valid.php
     * @return bool
     */
    public function valid(): bool
    {
        return (bool)$this->current;
    }

    /**
     * 重置迭代器到起始位置。
     * 初始化相关属性，包括偏移量和键值。
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->current = $this->start; // 重置到起始节点
        $this->offset = -1; // 偏移量初始化
        $this->key = 0; // 键值初始化
        $this->letters = []; // 清空字符缓存

        $this->next(); // 开始下一次迭代
    }
}
