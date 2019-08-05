<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\DeployStrategy;

abstract class DeployStrategyAbstract
{
    /**
     * The path mappings to map project's directories
     *
     * @var array
     */
    protected $mappings = [];

    /**
     * The current mapping of the deployment iteration
     *
     * @var array
     */
    protected $currentMapping = [];

    /**
     * The List of entries which files should not get deployed
     *
     * @var array
     */
    protected $ignoredMappings = [];


    /**
     * The project base directory
     *
     * @var string
     */
    protected $destDir;

    /**
     * The module's base directory
     *
     * @var string
     */
    protected $sourceDir;

    /**
     * If set overrides existing files
     *
     * @var bool
     */
    protected $isForced = false;

    public function __construct(string $sourceDir, string $destDir)
    {
        $this->destDir = $destDir;
        $this->sourceDir = $sourceDir;
    }

    /**
     * Executes the deployment strategy for each mapping
     * @throws \ErrorException
     */
    public function deploy(): self
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
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Sets path mappings to map project's directories
     */
    public function setMappings(array $mappings): self
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
    public function create(string $source, string $dest): bool
    {
        if ($this->isDestinationIgnored($dest)) {
            return false;
        }

        $sourcePath = $this->getSourceDir() . '/' . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . '/' . $dest;

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
                    $newDest = \substr($destPath . '/' . \basename($match), \strlen($this->getDestDir()));
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
        $destination = '/' . $destination;
        $destination = \str_replace(['/./', '//'], '/', $destination);
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

    /**
     * Removes the module's files in the given path from the target dir
     * @throws \ErrorException
     */
    public function clean(): self
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
    public function remove(string $source, string $dest): void
    {
        $sourcePath = $this->getSourceDir() . '/' . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . '/' . $dest;

        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!\file_exists($sourcePath)) {
            $this->removeContentOfCategory($sourcePath, $destPath);
            return;
        }

        if (\file_exists($sourcePath) && \is_dir($sourcePath)) {
            $this->removeContentOfCategory($sourcePath . '/*', $destPath);
            @\rmdir($destPath);
            return;
        }

        // MP Avoid removing whole folders in case the file is not 100% well-written
        if (\basename($sourcePath) !== \basename($destPath)) {
            $destPath .= '/' . \basename($source);
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
                $newDest = \substr($destPath . '/' . \basename($match), \strlen($this->getDestDir()));
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
    public static function rmdirRecursive(string $dir): void
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
    public function rmEmptyDirsRecursive(string $dir, string $stopDir = ''): void
    {
        $absoluteDir = $this->getDestDir() . '/' . $dir;
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
                $absoluteParentDir = $this->getDestDir() . '/' . $parentDir;
                if (!empty($stopDir) || (\realpath($stopDir) !== \realpath($absoluteParentDir))) {
                    // Remove the parent directory if it is empty
                    $this->rmEmptyDirsRecursive($parentDir);
                }
            }
        }
    }

    /**
     * If set overrides existing files
     */
    public function isForced(): bool
    {
        return $this->isForced;
    }

    /**
     * Setter for isForced property
     */
    public function setIsForced($forced = true): self
    {
        $this->isForced = (bool)$forced;

        return $this;
    }

    /**
     * Gets the current mapping used on the deployment iteration
     */
    public function getCurrentMapping(): array
    {
        return $this->currentMapping;
    }

    /**
     * Sets the current mapping used on the deployment iteration
     */
    public function setCurrentMapping(array $mapping): self
    {
        $this->currentMapping = $mapping;

        return $this;
    }

    /**
     * sets the current ignored mappings
     */
    public function setIgnoredMappings(array $ignoredMappings): self
    {
        $this->ignoredMappings = $ignoredMappings;

        return $this;
    }
}
