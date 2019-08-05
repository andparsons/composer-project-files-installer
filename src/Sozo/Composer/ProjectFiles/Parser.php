<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles;

interface Parser
{
    /**
     * Return the mappings in an array
     *
     * @throws \ErrorException
     */
    public function getMappings(): array;
}
