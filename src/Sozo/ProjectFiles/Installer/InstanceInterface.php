<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Installer;

use Sozo\ProjectFiles\InstallStrategy\InstallStrategyInterface;

interface InstanceInterface
{
    public function getPackageName(): string;

    public function setPackageName(string $packageName): self;

    public function getInstallStrategy(): InstallStrategyInterface;

    public function setInstallStrategy(InstallStrategyInterface $installStrategy): self;
}
