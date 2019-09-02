<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

use Sozo\ProjectFiles\Installer\InstanceInterface;

interface InstallerInterface
{
    public function addPackage(InstanceInterface $package): void;

    public function setSortPriority($priorities): void;

    public function doInstall(): void;
}
