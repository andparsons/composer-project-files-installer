<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

class MapParser implements ParserInterface
{
    /**
     * Variants on each prefix that path mappings are checked against.
     *
     * @var array
     */
    protected $pathPrefixVariants = ['', './'];

    /**
     * Path mapping prefixes that need to be translated
     * (i.e. to use a public directory as the web server root).
     *
     * @var array
     */
    protected $pathPrefixTranslations = [];

    /** @var string */
    protected $pathSuffix;
    /** @var array */
    protected $mappings = [];

    public function __construct($mappings, array $translations, string $pathSuffix)
    {
        $this->pathPrefixTranslations = $this->createPrefixVariants($translations);
        $this->pathSuffix = $pathSuffix;

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

    /**
     * Given an array of path mapping translations, combine them with a list of starting variations.
     * This is so that a translation for 'js' will also match path mappings beginning with './js'.
     */
    protected function createPrefixVariants(array $translations): array
    {
        $newTranslations = [];
        foreach ($translations as $key => $value) {
            foreach ($this->pathPrefixVariants as $variant) {
                $newTranslations[$variant . $key] = $value;
            }
        }

        return $newTranslations;
    }

    /**
     * Given a list of path mappings, check if any of the targets are for directories that
     * have been moved under the public directory.
     * If so, update the target paths to include 'public/'.
     * As no standard path mappings should ever start with 'public/', and  path mappings
     * that already include the public directory should always have paths starting with 'public/',
     * it should be safe to call multiple times on either.
     */
    public function translatePathMappings(array $mappings): array
    {
        // each element of $mappings is an array with two elements; first is
        // the source and second is the target
        foreach ($mappings as &$mapping) {
            foreach ($this->pathPrefixTranslations as $prefix => $translate) {
                if (\strpos($mapping[1], $prefix) === 0) {
                    // replace the old prefix with the translated version
                    $mapping[1] = $translate . \substr($mapping[1], \strlen($prefix));
                    // should never need to translate a prefix more than once
                    // per path mapping
                    break;
                }
            }
            //Adding path Suffix to the mapping info.
            $mapping[1] = $this->pathSuffix . $mapping[1];
        }
        return $mappings;
    }

}
