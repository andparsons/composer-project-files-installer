<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\InstallStrategy;

abstract class InstallStrategyAbstract implements InstallStrategyInterface
{
    /**
     * The path mappings to map project's directories
     *
     * @var array
     */
    private $mappings = [];

    /**
     * The current mapping of the install iteration
     *
     * @var array
     */
    private $currentMapping = [];

    /**
     * The List of entries which files should not get installed
     *
     * @var array
     */
    private $ignoredMappings = [];

    /**
     * The project base directory
     *
     * @var string
     */
    private $destDir;

    /**
     * The module's base directory
     *
     * @var string
     */
    private $sourceDir;

    /**
     * If set overrides existing files
     *
     * @var bool
     */
    private $isForced = false;

    public function __construct(string $sourceDir, string $destDir)
    {
        $this->destDir = $destDir;
        $this->sourceDir = $sourceDir;
    }

    /** @inheritDoc */
    public function install(): InstallStrategyInterface
    {
        foreach ($this->getMappings() as $data) {
            [$source, $dest] = $data;
            $this->setCurrentMapping($data);
            $this->create($source, $dest);
        }
        return $this;
    }

    /**
     * Returns the path mappings to map project's directories
     */
    protected function getMappings(): array
    {
        return $this->mappings;
    }

    /** @inheritDoc */
    public function setMappings(array $mappings): InstallStrategyInterface
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * Normalize mapping parameters using a glob wildcard.
     *
     * Delegate the creation of the module's files in the given destination.
     *
     * @throws \ErrorException
     */
    protected function create(string $source, string $dest): bool
    {
        if ($this->isDestinationIgnored($dest)) {
            return false;
        }

        $sourcePath = $this->getSourceDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . \DIRECTORY_SEPARATOR . $dest;

        // Create target directory if it ends with a directory separator
        if (!\file_exists($destPath) &&
            !\is_dir($sourcePath) &&
            \in_array(\substr($destPath, -1), ['/', '\\'])
        ) {
            if (!\mkdir($destPath, 0777, true) && !\is_dir($destPath)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destPath));
            }
            $destPath = $this->removeTrailingSlash($destPath);
        }

        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!\file_exists($sourcePath)) {
            // Handle globing
            $matches = \glob($sourcePath);
            if ($matches) {
                foreach ($matches as $match) {
                    $newDest = \substr($destPath . \DIRECTORY_SEPARATOR . \basename($match),
                        \strlen($this->getDestDir()));
                    $newDest = \ltrim($newDest, ' \\/');
                    $this->create(\substr($match, \strlen($this->getSourceDir()) + 1), $newDest);
                }
                return true;
            }

            // Source file isn't a valid file or glob
            throw new \ErrorException(\sprintf('Source %s does not exist', $sourcePath));
        }
        return $this->createDelegate($source, $dest);
    }

    protected function isDestinationIgnored(string $destination): bool
    {
        $destination = \DIRECTORY_SEPARATOR . $destination;
        $destination = \str_replace(['/./', '//'], \DIRECTORY_SEPARATOR, $destination);
        foreach ($this->ignoredMappings as $ignored) {
            if (0 === \strpos($ignored, $destination)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the current path of the extension
     */
    protected function getSourceDir(): string
    {
        return $this->sourceDir;
    }

    protected function removeTrailingSlash($path): string
    {
        return \rtrim($path, ' \\/');
    }

    /**
     * Returns the destination dir
     */
    protected function getDestDir(): string
    {
        return $this->destDir;
    }

    /**
     * Create the module's files in the given destination.
     *
     * NOTE: source and dest have to be passed as relative directories, like they are listed in the mapping
     */
    abstract protected function createDelegate(string $source, string $dest): bool;

    /** @inheritDoc */
    public function clean(): InstallStrategyInterface
    {
        foreach ($this->getMappings() as $data) {
            [$source, $dest] = $data;
            $this->remove($source, $dest);
            $this->rmEmptyDirsRecursive(\dirname($dest), $this->getDestDir());
        }
        return $this;
    }

    /**
     * Remove (unlink) the destination file
     *
     * @throws \ErrorException
     */
    protected function remove(string $source, string $dest): void
    {
        $sourcePath = $this->getSourceDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . \DIRECTORY_SEPARATOR . $dest;

        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!\file_exists($sourcePath)) {
            $this->removeContentOfCategory($sourcePath, $destPath);
            return;
        }

        if (\file_exists($sourcePath) && \is_dir($sourcePath)) {
            $this->removeContentOfCategory($sourcePath . \DIRECTORY_SEPARATOR . '*', $destPath);
            @\rmdir($destPath);
            return;
        }

        // MP Avoid removing whole folders in case the file is not 100% well-written
        if (\basename($sourcePath) !== \basename($destPath)) {
            $destPath .= \DIRECTORY_SEPARATOR . \basename($source);
        }
        self::rmdirRecursive($destPath);
    }

    /**
     * Search and remove content of category
     *
     * @throws \ErrorException
     */
    protected function removeContentOfCategory(string $sourcePath, string $destPath): void
    {
        $sourcePath = \preg_replace('#/\*$#', '/{,.}*', $sourcePath);
        $matches = \glob($sourcePath, \GLOB_BRACE);
        if ($matches) {
            foreach ($matches as $match) {
                if (\preg_match("#/\.{1,2}$#", $match)) {
                    continue;
                }
                $newDest = \substr($destPath . \DIRECTORY_SEPARATOR . \basename($match), \strlen($this->getDestDir()));
                $newDest = \ltrim($newDest, ' \\/');
                $this->remove(\substr($match, \strlen($this->getSourceDir()) + 1), $newDest);
            }
            return;
        }

        // Source file isn't a valid file or glob
        throw new \ErrorException(\sprintf('Source %s does not exist', $sourcePath));
    }

    /**
     * Recursively removes the specified directory or file
     */
    protected static function rmdirRecursive(string $dir): void
    {
        if (\is_dir($dir)) {
            (new \Composer\Util\Filesystem())->removeDirectory($dir);
        } else {
            @\unlink($dir);
        }

        return;
    }

    /**
     * Remove an empty directory branch up to $stopDir, or stop at the first non-empty parent
     */
    protected function rmEmptyDirsRecursive(string $dir, string $stopDir = ''): void
    {
        $absoluteDir = $this->getDestDir() . \DIRECTORY_SEPARATOR . $dir;
        if (\is_dir($absoluteDir)) {
            /** @var \RecursiveDirectoryIterator $iterator */
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluteDir),
                \RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($iterator as $item) {
                $path = (string)$item;
                if (!\strcmp($path, '.') || !\strcmp($path, '..')) {
                    continue;
                }
                // The directory contains something, do not remove
                return;
            }
            // RecursiveIteratorIterator have opened handle on $absoluteDir
            // that cause Windows to block the directory and not remove it until
            // the iterator will be destroyed.
            unset($iterator);

            // The specified directory is empty
            if (@\rmdir($absoluteDir)) {
                // If the parent directory doesn't match the $stopDir and it's empty, remove it, too
                $parentDir = \dirname($dir);
                $absoluteParentDir = $this->getDestDir() . \DIRECTORY_SEPARATOR . $parentDir;
                if (!empty($stopDir) || (\realpath($stopDir) !== \realpath($absoluteParentDir))) {
                    // Remove the parent directory if it is empty
                    $this->rmEmptyDirsRecursive($parentDir);
                }
            }
        }
    }

    /** @inheritDoc */
    public function setIgnoredMappings(array $ignoredMappings): InstallStrategyInterface
    {
        $this->ignoredMappings = $ignoredMappings;

        return $this;
    }

    /**
     * If set overrides existing files
     */
    protected function isForced(): bool
    {
        return $this->isForced;
    }

    /** @inheritDoc */
    public function setIsForced($forced = true): InstallStrategyInterface
    {
        $this->isForced = (bool)$forced;

        return $this;
    }

    /**
     * Gets the current mapping used on the install iteration
     */
    protected function getCurrentMapping(): array
    {
        return $this->currentMapping;
    }

    /**
     * Sets the current mapping used on the install iteration
     */
    protected function setCurrentMapping(array $mapping): self
    {
        $this->currentMapping = $mapping;

        return $this;
    }
}
