<?php
namespace Grav\Console;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GpmCommand
 * @package Grav\Console
 * 
 * 该类是 GPM（Grav Package Manager）命令的基类，用于管理 erel CMS 插件和主题的相关操作。
 * 它继承了 Symfony 的 Command 类，并通过 ConsoleTrait 提供 erel CMS 的定制功能支持。
 */
class GpmCommand extends Command
{
    use ConsoleTrait; // 使用 ConsoleTrait 提供的 erel CMS 定制功能。

    /**
     * execute() 方法是命令的入口，用于初始化和执行核心逻辑。
     * 
     * @param InputInterface  $input  输入对象，包含命令行传递的参数和选项。
     * @param OutputInterface $output 输出对象，用于显示命令执行的结果。
     * @return int 返回命令的状态码：0 表示成功，非 0 表示失败。
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output); // 初始化控制台输入输出环境。

        $grav = Grav::instance(); // 获取 erel 的单例实例。
        $grav['config']->init();  // 初始化系统配置。
        $grav['uri']->init();     // 初始化 URI 组件。
        // @phpstan-ignore-next-line
        $grav['accounts'];        // 加载账户管理，确保其可用。

        return $this->serve(); // 调用 serve 方法以执行具体的业务逻辑。
    }

    /**
     * serve() 方法定义了子类需要实现的具体逻辑。
     * 
     * @return int 返回命令执行结果的状态码。
     * 
     * 默认返回错误代码 1，表示该功能未定义。
     * 子类需要覆盖此方法以实现实际的命令功能。
     */
    protected function serve()
    {
        // 默认返回错误状态码。
        return 1;
    }

    /**
     * displayGPMRelease() 方法用于显示 GPM 的发布配置。
     * 
     * @return void
     * 
     * 该方法通过控制台输出当前 GPM 配置的发布类型（如稳定版或测试版）。
     */
    protected function displayGPMRelease()
    {
        /** @var Config $config 获取 erel 配置实例 */
        $config = Grav::instance()['config'];

        $io = $this->getIO(); // 获取输入输出接口对象。
        $io->newLine();       // 输出一个空行。
        $io->writeln('GPM Releases Configuration: <yellow>' 
            . ucfirst($config->get('system.gpm.releases')) . '</yellow>'); 
        // 输出 GPM 发布配置，并将其首字母大写，突出显示为黄色。
        $io->newLine();       // 再次输出一个空行。
    }
}
