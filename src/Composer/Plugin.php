<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Composer;

use Sozo\ProjectFiles\InstallerInterface;
use Sozo\ProjectFiles\Types\ExtraTypes;

class Plugin implements \Composer\Plugin\PluginInterface, \Composer\EventDispatcher\EventSubscriberInterface
{
    /** @var \Composer\IO\IOInterface */
    private $io;

    /** @var InstallerInterface */
    private $installer;

    /** @var \Composer\Composer */
    private $composer;

    /** @var \Composer\Util\Filesystem */
    private $filesystem;

    /** @var ComposerInstallerInterface */
    private $composerInstaller;

    /** @inheritDoc */
    public static function getSubscribedEvents(): array
    {
        return [
            \Composer\Script\ScriptEvents::POST_INSTALL_CMD => [
                ['onNewCodeEvent', 0],
            ],
            \Composer\Script\ScriptEvents::POST_UPDATE_CMD => [
                ['onNewCodeEvent', 0],
            ]
        ];
    }

    /** @noinspection PhpUnused */
    /** event listener is named this way, as it listens for events leading to changed code files */
    public function onNewCodeEvent(\Composer\Script\Event $event = null): void
    {
        $this->getInstaller()->doInstall();

        return;
    }

    /**
     * @return InstallerInterface
     */
    protected function getInstaller(): InstallerInterface
    {
        if ($this->installer === null) {
            $this->installer = new \Sozo\ProjectFiles\Installer($this->io);
        }
        return $this->installer;
    }

    /** @inheritDoc */
    public function activate(\Composer\Composer $composer, \Composer\IO\IOInterface $io): void
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new \Composer\Util\Filesystem();
        $this->initInstaller($composer);
        $this->getComposerInstaller()->setInstaller($this->getInstaller());
        $composer->getInstallationManager()->addInstaller($this->getComposerInstaller());

        return;
    }

    protected function initInstaller(\Composer\Composer $composer): void
    {
        $extra = $composer->getPackage()->getExtra();
        $sortPriority = $extra[ExtraTypes::FILES_PRIORITY] ?? [];
        $this->getInstaller()->setSortPriority($sortPriority);

        return;
    }

    protected function getComposerInstaller(): ComposerInstallerInterface
    {
        if ($this->composerInstaller === null) {
            try {
                $this->composerInstaller = new ComposerInstaller($this->io, $this->composer);
            } catch (\ErrorException $e) {
                $this->io->write($e->getMessage());
            }
        }
        return $this->composerInstaller;
    }
}
