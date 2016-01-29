<?php
/*
 * mercari/composer-diff-plugin
 * @license MIT https://github.com/mercari/composer-diff-plugin
 */
namespace Mercari\ComposerDiffPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;

use Composer\Script\ScriptEvents;
use Composer\Script\Event;
use Symfony\Component\Console\Helper\Table;

class DiffPlugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer */
    protected $composer;
    /** @var IOInterface */
    protected $io;

    /** @var array pre */
    protected $before = array();
    /** @var array post */
    protected $after = array();

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_UPDATE_CMD => array(
                'onPreUpdate',
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                'onPostUpdate',
            ),
        );
    }

    public function onPreUpdate(Event $arg)
    {
        $this->before = $this->licenses();
    }

    public function onPostUpdate(Event $arg)
    {
        $this->after = $this->licenses();
        $io = $this->io;

        $before = $this->before;
        $after = $this->after;

        $io->write("\n" . '<info>[[[ library version information ]]]</info>');
        if ($before == $after) {
            $io->write('no change!'); 
            return;
        }
        $output = IO::getSymfonyOutput($io);

        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setVerticalBorderChar('');
        $table->getStyle()->setCellRowContentFormat('%s  ');

        // deleted packages
        $minus = array_diff_key($before, $after);
        if ($minus) {
            foreach ($minus as $name => $val) {
                $table->addRow(array(
                    '<fg=red>[-]</>',
                    $name,
                    $val['version'],
                    implode(', ', $val['license']) ?: 'none',
                ));

                unset($before[$name]);
            }
        }

        // added packages
        $plus = array_diff_key($after, $before);
        if ($plus) {
            foreach ($plus as $name => $val) {
                $table->addRow(array(
                    '<fg=green>[+]</>',
                    $name,
                    $val['version'],
                    implode(', ', $val['license']) ?: 'none',
                ));

                unset($after[$name]);
            }
        }

        // changed packages
        $diff = array();
        foreach ($before as $name => $val) {
            if ($val == $after[$name]) {
                continue; //no change
            }
            $diff[$name] = array($val, $after[$name]);
        }
        if ($diff) {
            foreach ($diff as $name => $val) {
                $a = $val[0]['version'];
                $b = $val[1]['version'];
                $vd = version_compare($a, $b);
                switch ($vd) {
                case 1:
                    $change = '<fg=yellow>[d]</>';
                    break;
                case -1:
                    $change = '<fg=yellow>[u]</>';
                    break;
                case 0:
                    $change = '[?]';
                    break;
                }
                $table->addRow(array($change, $name, "$a=>$b", ''));
            }
        }
        $table->render();

        $this->writeLibraryList($this->after);
    }

    /**
     * to array composer licenses
     */
    protected function licenses()
    {
        $composer = $this->composer;

        $root = $composer->getPackage();
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $packages = array();
        foreach ($repo->getPackages() as $p) {
            $packages[$p->getName()] = $p;
        }

        ksort($packages);

        $libs = array();
        foreach ($packages as $p) {
            $libs[$p->getPrettyName()] = array(
                'version' => $p->getFullPrettyVersion(),
                'license' => $p->getLicense(),
            );
        }

        return $libs;
    }

    /**
     * write composer.list
     */
    protected function writeLibraryList($packages)
    {
        $fp = fopen('composer.list', 'wb');

        $output = new \Symfony\Component\Console\Output\StreamOutput($fp);
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setVerticalBorderChar('');
        $table->getStyle()->setCellRowContentFormat('%s  ');
        $table->setHeaders(array('Name', 'Version', 'License'));
        foreach ($packages as $name => $p) {
            $table->addRow(array(
                $name,
                $p['version'],
                implode(', ', $p['license']) ?: 'none',
            ));
        }
        $table->render();

        unset($table);
        fclose($fp);
    }
}
