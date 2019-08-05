<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles;

class MapParser extends PathTranslationParser
{
    /** @var array */
    protected $mappings = [];

    public function __construct($mappings, array $translations, string $pathSuffix)
    {
        parent::__construct($translations, $pathSuffix);

        $this->setMappings($mappings);
    }

    public function getMappings(): array
    {
        return $this->mappings;
    }

    public function setMappings($mappings): self
    {
        $this->mappings = $this->translatePathMappings($mappings);

        return $this;
    }
}
