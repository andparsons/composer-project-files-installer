<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Installer;

use Sozo\ProjectFiles\InstallStrategy\InstallStrategyInterface;

class Instance implements InstanceInterface
{
    /** @var string */
    private $packageName;

    /** @var InstallStrategyInterface */
    private $installStrategy;

    /** @inheritDoc */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /** @inheritDoc */
    public function setPackageName(string $packageName): InstanceInterface
    {
        $this->packageName = $packageName;

        return $this;
    }

    /** @inheritDoc */
    public function getInstallStrategy(): InstallStrategyInterface
    {
        return $this->installStrategy;
    }

    /** @inheritDoc */
    public function setInstallStrategy(InstallStrategyInterface $installStrategy): InstanceInterface
    {
        $this->installStrategy = $installStrategy;

        return $this;
    }
}
