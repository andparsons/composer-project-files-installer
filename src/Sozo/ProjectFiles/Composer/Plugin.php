<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Composer;

use Sozo\ProjectFiles\DeployManager;

class Plugin implements \Composer\Plugin\PluginInterface, \Composer\EventDispatcher\EventSubscriberInterface
{
    /** @var \Composer\IO\IOInterface */
    private $io;

    /** @var DeployManager */
    private $deployManager;

    /** @var \Composer\Composer */
    private $composer;

    /** @var \Composer\Util\Filesystem */
    private $filesystem;

    /** @var Installer */
    private $installer;

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
    public function onNewCodeEvent(\Composer\Script\Event $event): void
    {
        if ($this->io->isDebug()) {
            $this->io->write(\sprintf('start deploy via deployManager: %s', $event));
        }

        $this->getDeployManager()->doDeploy();

        return;
    }

    public function activate(\Composer\Composer $composer, \Composer\IO\IOInterface $io): void
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new \Composer\Util\Filesystem();
        $this->initDeployManager($composer);
        $this->getInstaller()->setDeployManager($this->getDeployManager());
        $composer->getInstallationManager()->addInstaller($this->getInstaller());

        return;
    }

    protected function initDeployManager(\Composer\Composer $composer): void
    {
        $extra = $composer->getPackage()->getExtra();
        $sortPriority = $extra['deploy-sort-priority'] ?? [];
        $this->getDeployManager()->setSortPriority($sortPriority);

        return;
    }

    /**
     * @return DeployManager
     */
    public function getDeployManager(): DeployManager
    {
        if ($this->deployManager === null) {
            $this->deployManager = new DeployManager($this->io);
        }
        return $this->deployManager;
    }

    /**
     * @return Installer
     */
    public function getInstaller(): Installer
    {
        if ($this->installer === null) {
            try {
                $this->installer = new Installer($this->io, $this->composer);
            } catch (\ErrorException $e) {
                $this->io->write($e->getMessage());
            }
        }
        return $this->installer;
    }
}
