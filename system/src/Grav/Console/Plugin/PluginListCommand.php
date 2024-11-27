<?php
namespace Grav\Console\Plugin;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Plugins;
use Grav\Console\ConsoleCommand;

/**
 * Class PluginListCommand
 * @package Grav\Console\Gpm
 * 
 * 该类用于列出已启用的 erel CMS 插件，并显示支持 CLI 功能的插件列表。
 * 它继承自 ConsoleCommand，为 erel 提供了命令行管理插件的功能。
 */
class PluginListCommand extends ConsoleCommand
{
    // 定义命令的默认名称为 `plugins:list`。
    protected static $defaultName = 'plugins:list';

    /**
     * 配置命令的元信息。
     * 
     * @return void
     * 
     * 此方法将命令设置为隐藏状态，不会显示在默认命令列表中。
     */
    protected function configure(): void
    {
        $this->setHidden(true); // 将命令设置为隐藏状态。
    }

    /**
     * serve() 方法是核心业务逻辑的实现。
     * 
     * @return int 返回命令执行状态码。0 表示成功，非 0 表示失败。
     * 
     * 该方法会输出支持 CLI 功能的插件列表，包括命令示例和用法说明。
     */
    protected function serve(): int
    {
        $bin = $this->argv; // 获取命令行参数（命令本身及其参数）。
        $pattern = '([A-Z]\w+Command\.php)'; // 匹配 CLI 命令文件的正则表达式。

        $io = $this->getIO(); // 获取输入输出接口对象，用于格式化输出。
        $io->newLine(); // 输出一个空行。
        $io->writeln('<red>Usage:</red>'); // 输出 "Usage:" 的标题。
        $io->writeln("  {$bin} [slug] [command] [arguments]"); // 显示通用命令用法。
        $io->newLine(); // 再次输出空行。
        $io->writeln('<red>Example:</red>'); // 输出 "Example:" 的标题。
        $io->writeln("  {$bin} error log -l 1 --trace"); // 显示命令使用示例。
        $io->newLine(); // 再次输出空行。
        $io->writeln('<red>Plugins with CLI available:</red>'); // 输出标题，列出支持 CLI 的插件。

        // 获取所有已安装的插件。
        $plugins = Plugins::all(); 
        $index = 0; // 插件索引，用于生成编号。
        foreach ($plugins as $name => $plugin) {
            if (!$plugin->enabled) { // 跳过未启用的插件。
                continue;
            }

            // 检查插件目录中是否包含 CLI 命令文件。
            $list = Folder::all(
                "plugins://{$name}", 
                ['compare' => 'Pathname', 'pattern' => '/\/cli\/' . $pattern . '$/usm', 'levels' => 1]
            );
            if (!$list) { // 如果没有 CLI 文件，跳过该插件。
                continue;
            }

            $index++; // 增加插件索引。
            $num = str_pad((string)$index, 2, '0', STR_PAD_LEFT); // 格式化编号（两位数字）。
            $io->writeln('  ' . $num . '. <red>' . str_pad($name, 15) . "</red> <white>{$bin} {$name} list</white>");
            // 输出插件名称及其对应的 CLI 命令格式。
        }

        return 0; // 返回成功状态码。
    }
}
