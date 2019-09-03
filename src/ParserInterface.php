<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

interface ParserInterface
{
    /**
     * Return the mappings in an array
     *
     * @throws \ErrorException
     */
    public function getMappings(): array;
}
