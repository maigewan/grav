<?php

/**
 * 遍历DOM文本和CDATA节点中的每个单词，
 * 同时记录它们在文档中的位置。
 *
 * 示例：
 *
 *  $doc = new DOMDocument();
 *  $doc->load('example.xml');
 *  foreach(new DOMWordsIterator($doc) as $word) echo $word;
 *
 * 作者：
 * pjgalbraith http://www.pjgalbraith.com
 * porneL http://pornel.net (基于DOMLettersIterator的代码改编，原始代码参见 http://pornel.net/source/domlettersiterator.php)
 * 授权协议：公共领域
 * 代码地址：https://github.com/antoligy/dom-string-iterators
 *
 * 实现Iterator接口，使字符串可以逐词迭代。
 *
 * @implements Iterator<int,string>
 */

final class DOMWordsIterator implements Iterator
{
    /** @var DOMElement 起始元素 */
    private $start;
    /** @var DOMElement|null 当前正在迭代的节点，可能为null表示迭代结束 */
    private $current;
    /** @var int 当前节点中单词的偏移量，初始值为-1 */
    private $offset = -1;
    /** @var int|null 当前的迭代键值 */
    private $key;
    /** @var array<int,array<int,int|string>>|null 当前节点中的单词数组，每个单词包含内容和偏移量 */
    private $words;

    /**
     * 构造函数，接收DOMElement或DOMDocument对象。
     * 如果传入的是DOMDocument对象，会提取其documentElement作为起始节点。
     *
     * @param DOMNode $el DOM元素或文档对象
     * @throws InvalidArgumentException 如果参数不是DOMElement或DOMDocument
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
     * 返回文本中的当前位置，包括DOMText节点、偏移量和单词数组。
     * 偏移量不是字节偏移量，如果要操作字符串，需要使用如mb_substr()等支持多字节操作的函数。
     * 如果迭代器结束，节点可能为NULL。
     *
     * @return array 包含三个元素：[DOMText节点或null, 当前偏移量, 单词数组]
     */
    public function currentWordPosition(): array
    {
        return [$this->current, $this->offset, $this->words];
    }

    /**
     * 返回当前正在迭代的DOMElement或NULL（当迭代器结束时）。
     * 当前元素是通过获取当前节点的父节点得到的。
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
     * 键值表示在文档中的单词位置，用于标识当前迭代状态。
     *
     * @link https://php.net/manual/en/iterator.key.php
     * @return int|null
     */
    public function key(): ?int
    {
        return $this->key;
    }

    /**
     * 移动到下一个单词。
     * 包括处理文本节点、CDATA段，以及遍历DOM树的逻辑。
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
            // 第一次进入节点时，按空格或换行符分割单词
            if ($this->offset === -1) {
                $this->words = preg_split(
                    "/[\n\r\t ]+/",
                    $this->current->textContent,
                    -1,
                    PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE
                ) ?: [];
            }

            $this->offset++;
            // 如果当前还有未迭代的单词，继续迭代
            if ($this->words && $this->offset < count($this->words)) {
                $this->key++;
                return;
            }

            // 当前节点中的单词已全部处理，重置偏移量
            $this->offset = -1;
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
     * 返回当前单词。
     * 如果当前节点为空或已遍历完，则返回NULL。
     *
     * @link https://php.net/manual/en/iterator.current.php
     * @return string|null
     */
    public function current(): ?string
    {
        return $this->words ? (string)$this->words[$this->offset][0] : null;
    }

    /**
     * 检查当前迭代位置是否有效。
     * 如果current()返回一个有效节点，则此方法返回true。
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
        $this->words = []; // 清空单词缓存

        $this->next(); // 开始下一次迭代
    }
}
