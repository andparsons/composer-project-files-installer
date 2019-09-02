<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

use Sozo\ProjectFiles\Installer\InstanceInterface;

class Installer implements InstallerInterface
{
    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * @var InstanceInterface[]
     */
    private $packages = [];

    /**
     * an array with package names as key and priorities as value
     *
     * @var array
     */
    private $sortPriority = [];

    public function __construct(\Composer\IO\IOInterface $io)
    {
        $this->io = $io;
    }

    /** @inheritDoc */
    public function addPackage(InstanceInterface $package): void
    {
        $this->packages[] = $package;

        return;
    }

    /** @inheritDoc */
    public function doInstall(): void
    {
        $this->sortPackages();

        /** @var InstanceInterface $package */
        foreach ($this->packages as $package) {
            try {
                $package->getInstallStrategy()->install();
            } catch (\ErrorException $e) {
                $this->io->write($e->getMessage());
            }
        }

        return;
    }

    /**
     * Uses the sortPriority Array to sort the packages.
     *
     * Highest priority first.
     * Copy gets per default higher priority then others
     */
    protected function sortPackages(): array
    {
        \usort(
            $this->packages,
            function ($a, $b) {
                $aPriority = $this->getPackagePriority($a);
                $bPriority = $this->getPackagePriority($b);
                if ($aPriority === $bPriority) {
                    return 0;
                }
                return ($aPriority > $bPriority) ? -1 : 1;
            }
        );

        return $this->packages;
    }

    /**
     * Determine the priority in which the package should be installed
     */
    protected function getPackagePriority(InstanceInterface $package): int
    {
        if (isset($this->sortPriority[$package->getPackageName()])) {
            return $this->sortPriority[$package->getPackageName()];
        }

        if ($package->getInstallStrategy() instanceof \Sozo\ProjectFiles\InstallStrategy\Copy) {
            return 101;
        }

        return 100;
    }

    /** @inheritDoc */
    public function setSortPriority($priorities): void
    {
        $this->sortPriority = $priorities;

        return;
    }
}
