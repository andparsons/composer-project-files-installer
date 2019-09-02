<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\InstallStrategy;

class None extends InstallStrategyAbstract
{
    /** Install nothing */
    protected function createDelegate(string $source, string $dest): bool
    {
        return true;
    }

    /** Install nothing */
    protected function create(string $source, string $dest): bool
    {
        return true;
    }
}
