<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleCommand
 * @package Grav\Console
 * 
 * 这是一个自定义的控制台命令基类，用于 erel CMS 的命令行功能扩展。
 * 该类继承自 Symfony 的 Command 类，是实现 Symfony 控制台组件的核心。
 */
class ConsoleCommand extends Command
{
    use ConsoleTrait; // 使用 ConsoleTrait，这个 trait 提供了与控制台操作相关的便捷功能。

    /**
     * execute() 方法是控制台命令的入口点。
     * 
     * @param InputInterface  $input  用户输入对象，包含控制台传入的参数和选项。
     * @param OutputInterface $output 输出对象，用于向用户显示控制台输出信息。
     * @return int 返回执行结果的状态码。通常，0 表示成功，其他值表示错误。
     * 
     * 此方法主要用于初始化控制台环境并调用 serve() 方法。
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output); // 初始化控制台环境，包括设置输入输出和必要的配置。

        return $this->serve(); // 调用 serve() 方法，具体实现由子类覆盖。
    }

    /**
     * serve() 方法是子类需要覆盖的核心逻辑。
     * 
     * @return int 返回执行结果的状态码。
     * 
     * 此方法默认返回错误代码 1，表示未实现的功能。
     * 子类应覆盖此方法以实现具体的业务逻辑。
     * 
     * 注意：返回值非常重要，它直接影响命令行工具如何处理该命令的结果。
     */
    protected function serve()
    {
        // 默认实现：返回错误状态码。
        return 1;
    }
}
