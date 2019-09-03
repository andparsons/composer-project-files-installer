<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\InstallStrategy;

class Symlink extends InstallStrategyAbstract
{
    /**
     * Creates a symlink with lots of error-checking
     *
     * @throws \ErrorException
     */
    protected function createDelegate(string $source, string $dest): bool
    {
        $filesystem = new \Composer\Util\Filesystem();
        $sourcePath = $this->getSourceDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($dest);

        if (!\is_file($sourcePath) && !\is_dir($sourcePath)) {
            throw new \ErrorException(\sprintf('Could not find path %s', $sourcePath));
        }

        if ($filesystem->isSymlinkedDirectory($destPath)) {
            $filesystem->removeDirectory($destPath);
        }

        // Create all directories up to one below the target if they don't exist
        $destDir = \dirname($destPath);
        $filesystem->ensureDirectoryExists($destDir);

        // Handle source to dir linking,
        if (\file_exists($destPath) && \is_dir($destPath)) {
            if (\basename($sourcePath) === \basename($destPath)) {
                if ($this->isForced()) {
                    self::rmdirRecursive($destPath);
                } else {
                    throw new \ErrorException(\sprintf('Target %s already exists (set extra.files-force to override)',
                        $dest));
                }
            } else {
                $destPath .= \DIRECTORY_SEPARATOR . \basename($source);
            }
            return $this->create($source, \substr($destPath, \strlen($this->getDestDir()) + 1));
        }

        // From now on $destPath can't be a directory, that case is already handled

        // If file exists and force is not specified, throw exception unless FORCE is set
        // existing symlinks are already handled
        if (\file_exists($destPath)) {
            if ($this->isForced()) {
                \unlink($destPath);
            }
        }

        // Create symlink
        if (false === \symlink($sourcePath, $destPath)) {
            throw new \ErrorException(\sprintf('An error occurred while creating symlink %s', $sourcePath));
        }

        return true;
    }
}
