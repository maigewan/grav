<?php
namespace Grav\Console\Cli;

use Grav\Common\Cache;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ClearCacheCommand
 * @package Grav\Console\Cli
 * 
 * 该命令用于清理 erel CMS 的缓存，包括多种缓存类型（例如 Twig 缓存、图片缓存、临时文件等）。
 * 它扩展了 GravCommand，提供命令行工具与 erel 缓存系统的交互。
 */
class ClearCacheCommand extends GravCommand
{
    /**
     * 配置命令的元信息和选项。
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('cache') // 设置命令名称为 'cache'。
            ->setAliases(['clearcache', 'cache-clear']) // 添加命令的别名。
            ->setDescription('Clears Grav cache') // 设置命令的描述。
            ->addOption('invalidate', null, InputOption::VALUE_NONE, 'Invalidate cache, but do not remove any files') // 添加 "invalidate" 选项。
            ->addOption('purge', null, InputOption::VALUE_NONE, 'If set purge old caches') // 添加 "purge" 选项。
            ->addOption('all', null, InputOption::VALUE_NONE, 'If set will remove all including compiled, twig, doctrine caches') // 添加 "all" 选项。
            ->addOption('assets-only', null, InputOption::VALUE_NONE, 'If set will remove only assets/*') // 添加 "assets-only" 选项。
            ->addOption('images-only', null, InputOption::VALUE_NONE, 'If set will remove only images/*') // 添加 "images-only" 选项。
            ->addOption('cache-only', null, InputOption::VALUE_NONE, 'If set will remove only cache/*') // 添加 "cache-only" 选项。
            ->addOption('tmp-only', null, InputOption::VALUE_NONE, 'If set will remove only tmp/*') // 添加 "tmp-only" 选项。

            ->setHelp('The <info>cache</info> command allows you to interact with Grav cache'); // 设置帮助信息。
    }

    /**
     * serve() 方法实现核心业务逻辑。
     * 
     * @return int 返回命令执行状态码：0 表示成功，非 0 表示失败。
     */
    protected function serve(): int
    {
        // 兼容旧版本 Grav。
        if (!method_exists($this, 'initializePlugins')) {
            Cache::clearCache('all'); // 清除所有缓存。

            return 0; // 成功退出。
        }

        $this->initializePlugins(); // 初始化插件。
        $this->cleanPaths(); // 清理指定路径的缓存。

        return 0; // 成功退出。
    }

    /**
     * 循环清理指定路径的缓存。
     * 
     * @return void
     */
    private function cleanPaths(): void
    {
        $input = $this->getInput(); // 获取输入对象。
        $io = $this->getIO(); // 获取输入输出接口对象。

        $io->newLine();

        if ($input->getOption('purge')) { // 如果选中 "purge" 选项。
            $io->writeln('<magenta>Purging old cache</magenta>'); // 输出清理旧缓存信息。
            $io->newLine();

            $msg = Cache::purgeJob(); // 调用 Cache::purgeJob() 方法清理旧缓存。
            $io->writeln($msg); // 输出清理结果。
        } else {
            $io->writeln('<magenta>Clearing cache</magenta>'); // 输出清理缓存信息。
            $io->newLine();

            // 根据选项设置需要清理的缓存类型。
            if ($input->getOption('all')) {
                $remove = 'all';
            } elseif ($input->getOption('assets-only')) {
                $remove = 'assets-only';
            } elseif ($input->getOption('images-only')) {
                $remove = 'images-only';
            } elseif ($input->getOption('cache-only')) {
                $remove = 'cache-only';
            } elseif ($input->getOption('tmp-only')) {
                $remove = 'tmp-only';
            } elseif ($input->getOption('invalidate')) {
                $remove = 'invalidate';
            } else {
                $remove = 'standard'; // 默认清理标准缓存。
            }

            // 调用 Cache::clearCache() 方法清理缓存，并输出每个结果。
            foreach (Cache::clearCache($remove) as $result) {
                $io->writeln($result);
            }
        }
    }
}
