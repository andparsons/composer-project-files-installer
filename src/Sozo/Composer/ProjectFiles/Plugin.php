<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles;

class Plugin implements \Composer\Plugin\PluginInterface, \Composer\EventDispatcher\EventSubscriberInterface
{
    /** @var \Composer\IO\IOInterface */
    protected $io;

    /** @var DeployManager */
    protected $deployManager;

    /** @var \Composer\Composer */
    protected $composer;

    /** @var \Composer\Util\Filesystem */
    protected $filesystem;

    /** @var Installer */
    protected $installer;

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

    public function activate(\Composer\Composer $composer, \Composer\IO\IOInterface $io): void
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new \Composer\Util\Filesystem();
        try {
            $this->installer = new Installer($io, $composer);
        } catch (\ErrorException $e) {
            $this->io->write($e->getMessage());
        }
        $this->initDeployManager($composer, $io);
        $this->installer->setDeployManager($this->deployManager);
        $composer->getInstallationManager()->addInstaller($this->installer);

        return;
    }

    protected function initDeployManager(\Composer\Composer $composer, \Composer\IO\IOInterface $io): void
    {
        $this->deployManager = new DeployManager($io);

        $extra = $composer->getPackage()->getExtra();
        $sortPriority = $extra['deploy-sort-priority'] ?? [];
        $this->deployManager->setSortPriority($sortPriority);

        return;
    }
    /** @noinspection PhpUnused */
    /** event listener is named this way, as it listens for events leading to changed code files */
    public function onNewCodeEvent(\Composer\Script\Event $event): void
    {
        if ($this->io->isDebug()) {
            $this->io->write(\sprintf('start deploy via deployManager: %s', $event));
        }

        $this->deployManager->doDeploy();

        return;
    }
}
