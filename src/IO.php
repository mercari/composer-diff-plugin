<?php
/*
 * mercari/composer-diff-plugin
 * @license MIT https://github.com/mercari/composer-diff-plugin
 */
namespace Mercari\ComposerDiffPlugin;

class IO extends \Composer\IO\ConsoleIO
{
    public static function getSymfonyInput(\Composer\IO\ConsoleIO $io)
    {
        return $io->input;
    }

    public static function getSymfonyOutput(\Composer\IO\ConsoleIO $io)
    {
        return $io->output;
    }
}
