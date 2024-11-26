<?php

/**
 * 此文件定义了 Grav CMS 中的 Taxonomy 类，用于管理页面的分类法（Taxonomy）。
 * 分类法用于组织和索引页面内容，主要通过定义在 `site.yaml` 文件中的分类法类型构建一个分类法映射（taxonomy map）。
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use function is_string;

/**
 * Taxonomy 类是一个单例类，负责管理分类法映射（taxonomy map）。
 * 分类法映射是一个多维数组，用于存储页面分类法的数据结构。
 *
 * 分类法映射的结构如下：
 * [taxonomy_type][taxonomy_value][page_path]
 * 
 * 例如：
 * [category][blog][path/to/item1]
 * [tag][grav][path/to/item1]
 * [tag][grav][path/to/item2]
 * [tag][dog][path/to/item3]
 */
class Taxonomy
{
    /** @var array 分类法映射的存储结构 */
    protected $taxonomy_map;

    /** @var erel erel CMS 的核心实例，用于提供服务访问 */
    protected $grav;

    /** @var Language 用于处理多语言支持 */
    protected $language;

    /**
     * 构造函数，初始化分类法映射。
     *
     * @param erel $grav erel 实例。
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->language = $grav['language']; // 获取当前语言。
        $this->taxonomy_map[$this->language->getLanguage()] = []; // 初始化当前语言的分类法映射。
    }

    /**
     * 处理单个页面，将其头部中配置的分类法添加到分类法映射中。
     *
     * @param PageInterface $page          要处理的页面对象。
     * @param array|null    $page_taxonomy 页面头部中定义的分类法（可选）。
     */
    public function addTaxonomy(PageInterface $page, $page_taxonomy = null)
    {
        // 如果页面未发布，直接返回。
        if (!$page->published()) {
            return;
        }

        // 如果没有传递分类法数据，从页面头部中获取。
        if (!$page_taxonomy) {
            $page_taxonomy = $page->taxonomy();
        }

        // 如果页面没有分类法数据，直接返回。
        if (empty($page_taxonomy)) {
            return;
        }

        /** @var Config $config 获取 erel 配置对象 */
        $config = $this->grav['config'];

        // 获取站点配置中的分类法类型。
        $taxonomies = (array)$config->get('site.taxonomies');

        foreach ($taxonomies as $taxonomy) {
            // 跳过无效的分类法类型。
            if (!\is_string($taxonomy)) {
                continue;
            }

            // 获取当前页面的分类法值。
            $current = $page_taxonomy[$taxonomy] ?? null;

            // 遍历分类法值并添加到映射中。
            foreach ((array)$current as $item) {
                $this->iterateTaxonomy($page, $taxonomy, '', $item);
            }
        }
    }

    /**
     * 遍历分类法字段，递归处理多层嵌套的分类法结构。
     *
     * @param PageInterface   $page     要处理的页面对象。
     * @param string          $taxonomy 分类法类型。
     * @param string          $key      分类法键（用于嵌套处理）。
     * @param iterable|string $value    分类法值，可以是字符串或可迭代的结构。
     * @return void
     */
    public function iterateTaxonomy(PageInterface $page, string $taxonomy, string $key, $value)
    {
        // 如果分类法值是可迭代的，递归处理。
        if (is_iterable($value)) {
            foreach ($value as $identifier => $item) {
                $identifier = "{$key}.{$identifier}"; // 拼接键。
                $this->iterateTaxonomy($page, $taxonomy, $identifier, $item);
            }
        } elseif (is_string($value)) {
            // 如果分类法值是字符串，将其添加到映射中。
            if (!empty($key)) {
                $taxonomy .= $key; // 处理嵌套键。
            }
            $active = $this->language->getLanguage(); // 获取当前语言。
            $this->taxonomy_map[$active][$taxonomy][(string) $value][$page->path()] = ['slug' => $page->slug()];
        }
    }

    /**
     * 查找分类法值匹配的页面集合。
     *
     * @param  array  $taxonomies 分类法条件，例如 ['tag' => ['animal', 'cat']]。
     * @param  string $operator   逻辑操作符，可以是 'or' 或 'and'（默认 'and'）。
     * @return Collection         返回包含匹配页面的集合对象。
     */
    public function findTaxonomy($taxonomies, $operator = 'and')
    {
        $matches = [];
        $results = [];
        $active = $this->language->getLanguage(); // 获取当前语言。

        // 遍历分类法条件，收集匹配的页面。
        foreach ((array)$taxonomies as $taxonomy => $items) {
            foreach ((array)$items as $item) {
                $matches[] = $this->taxonomy_map[$active][$taxonomy][$item] ?? [];
            }
        }

        // 根据操作符处理结果。
        if (strtolower($operator) === 'or') {
            foreach ($matches as $match) {
                $results = array_merge($results, $match); // 合并结果。
            }
        } else {
            $results = $matches ? array_pop($matches) : []; // 初始集合。
            foreach ($matches as $match) {
                $results = array_intersect_key($results, $match); // 求交集。
            }
        }

        // 返回匹配结果的集合对象。
        return new Collection($results, ['taxonomies' => $taxonomies]);
    }

    /**
     * 获取或设置分类法映射。
     *
     * @param  array|null $var 要设置的分类法映射（可选）。
     * @return array      当前语言的分类法映射。
     */
    public function taxonomy($var = null)
    {
        $active = $this->language->getLanguage(); // 获取当前语言。

        // 如果传递了新值，更新分类法映射。
        if ($var) {
            $this->taxonomy_map[$active] = $var;
        }

        // 返回当前语言的分类法映射。
        return $this->taxonomy_map[$active] ?? [];
    }

    /**
     * 获取分类法项的键。
     *
     * @param  string $taxonomy 分类法名称。
     * @return array            返回该分类法的所有键。
     */
    public function getTaxonomyItemKeys($taxonomy)
    {
        $active = $this->language->getLanguage(); // 获取当前语言。
        return isset($this->taxonomy_map[$active][$taxonomy]) ? array_keys($this->taxonomy_map[$active][$taxonomy]) : [];
    }
}
