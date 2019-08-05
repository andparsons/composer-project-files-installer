<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles\DeployStrategy;

class None extends DeployStrategyAbstract
{
    /** Deploy nothing */
    public function createDelegate(string $source, string $dest): bool
    {
        return true;
    }

    /** Deploy nothing */
    public function create(string $source, string $dest): bool
    {
        return true;
    }
}
