<?php
namespace Grav\Console\TerminalObjects;

/**
 * Class Table
 * @package Grav\Console\TerminalObjects
 * 
 * @deprecated 1.7 请使用 Symfony Console Table 替代。
 * 
 * 该类用于在终端中创建表格，继承自 League\CLImate 提供的 Table 类。
 * 这是一个旧版本的表格生成器，已经被标记为废弃，建议使用更现代的 Symfony Console Table。
 */
class Table extends \League\CLImate\TerminalObject\Basic\Table
{
    /**
     * 生成并返回表格的最终数据。
     * 
     * @return array 返回一个数组，其中包含表格的所有行，包括边框和数据。
     * 
     * 该方法计算列宽度和表格宽度，生成表头行，构建每一行的数据，并最终添加表格边框。
     */
    public function result()
    {
        $this->column_widths = $this->getColumnWidths(); // 计算每一列的宽度。
        $this->table_width   = $this->getWidth();       // 计算表格的总宽度。
        $this->border        = $this->getBorder();      // 获取表格的边框样式。

        $this->buildHeaderRow(); // 构建表头行。

        // 遍历数据，为每一行生成表格内容。
        foreach ($this->data as $key => $columns) {
            $this->rows[] = $this->buildRow($columns); // 使用 buildRow 方法生成每一行数据。
        }

        $this->rows[] = $this->border; // 在表格最后添加边框行。

        return $this->rows; // 返回生成的表格行数组。
    }
}
