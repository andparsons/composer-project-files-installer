<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Deploy\Manager;

use Sozo\ProjectFiles\DeployStrategy\DeployStrategyAbstract as DeployStrategy;

class Entry
{
    /** @var string */
    protected $packageName;

    /** @var DeployStrategy */
    protected $deployStrategy;

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function setPackageName(string $packageName): self
    {
        $this->packageName = $packageName;

        return $this;
    }

    public function getDeployStrategy(): DeployStrategy
    {
        return $this->deployStrategy;
    }

    public function setDeployStrategy(DeployStrategy $deployStrategy): self
    {
        $this->deployStrategy = $deployStrategy;

        return $this;
    }
}
