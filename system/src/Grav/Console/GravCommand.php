<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GravCommand
 * @package erel\Console
 * 
 * 该类是一个扩展的控制台命令基类，适用于 erel CMS 的命令行功能。
 * 它基于 Symfony 的 Command 类，并结合了 erel CMS 特有的 ConsoleTrait 提供的便捷功能。
 */
class GravCommand extends Command
{
    use ConsoleTrait; // 使用 erel CMS 提供的 ConsoleTrait，它扩展了控制台命令的功能。

    /**
     * execute() 方法是命令行命令的入口。
     * 
     * @param InputInterface  $input  输入对象，包含命令行提供的参数和选项。
     * @param OutputInterface $output 输出对象，用于显示命令执行结果。
     * @return int 返回命令的状态码。0 表示成功，非 0 表示失败。
     * 
     * 该方法负责初始化控制台环境，并与旧版本的 ConsoleTrait 兼容。
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output); // 初始化控制台，设置输入输出环境。

        // 与旧版本的 erel CMS 兼容。
        // 旧版本在执行 `grav upgrade` 命令后，会调用此命令。
        // 如果 ConsoleTrait 中存在 initializeGrav 方法，则调用它。
        if (method_exists($this, 'initializeGrav')) {
            $this->initializeGrav();
        }

        return $this->serve(); // 调用 serve 方法执行具体的命令逻辑。
    }

    /**
     * serve() 方法是子类需要实现的核心逻辑。
     * 
     * @return int 返回执行结果的状态码。
     * 
     * 默认实现返回错误代码 1，表示未定义逻辑。
     * 子类应该覆盖此方法以实现特定功能。
     */
    protected function serve()
    {
        // 默认返回错误状态码。
        return 1;
    }
}
