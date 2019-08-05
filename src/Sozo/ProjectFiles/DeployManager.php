<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

use Sozo\ProjectFiles\Deploy\Manager\Entry;
use Sozo\ProjectFiles\DeployStrategy\Copy;

class DeployManager
{
    /**
     * @var Entry[]
     */
    protected $packages = [];

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * an array with package names as key and priorities as value
     *
     * @var array
     */
    protected $sortPriority = [];

    public function __construct(\Composer\IO\IOInterface $io)
    {
        $this->io = $io;
    }

    public function addPackage(Entry $package): void
    {
        $this->packages[] = $package;

        return;
    }

    public function setSortPriority($priorities): void
    {
        $this->sortPriority = $priorities;

        return;
    }

    /**
     * Uses the sortPriority Array to sort the packages.
     *
     * Highest priority first.
     * Copy gets per default higher priority then others
     *
     * @return array
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
     * Determine the priority in which the package should be deployed
     */
    private function getPackagePriority(Entry $package): int
    {
        if (isset($this->sortPriority[$package->getPackageName()])) {
            return $this->sortPriority[$package->getPackageName()];
        }

        if ($package->getDeployStrategy() instanceof Copy) {
            return 101;
        }

        return 100;
    }

    public function doDeploy(): void
    {
        $this->sortPackages();

        /** @var Entry $package */
        foreach ($this->packages as $package) {
            if ($this->io->isDebug()) {
                $this->io->write('start deploy for ' . $package->getPackageName());
            }
            try {
                $package->getDeployStrategy()->deploy();
            } catch (\ErrorException $e) {
                if ($this->io->isDebug()) {
                    $this->io->write($e->getMessage());
                }
            }
        }

        return;
    }
}
