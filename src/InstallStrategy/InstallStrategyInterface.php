<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\InstallStrategy;

interface InstallStrategyInterface
{
    /**
     * Executes the install strategy for each mapping
     * @throws \ErrorException
     */
    public function install(): self;

    /**
     * Sets path mappings to map project's directories
     */
    public function setMappings(array $mappings): self;

    /**
     * Removes the module's files in the given path from the target dir
     * @throws \ErrorException
     */
    public function clean(): self;

    /**
     * sets the current ignored mappings
     */
    public function setIgnoredMappings(array $ignoredMappings): self;

    /**
     * Setter for isForced property
     */
    public function setIsForced($forced = true): self;
}
