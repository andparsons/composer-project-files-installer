<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles\DeployStrategy;

class Symlink extends DeployStrategyAbstract
{
    /**
     * Creates a symlink with lots of error-checking
     *
     * @throws \ErrorException
     */
    public function createDelegate(string $source, string $dest): bool
    {
        $sourcePath = $this->getSourceDir() . '/' . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . '/' . $this->removeTrailingSlash($dest);

        if (!\is_file($sourcePath) && !\is_dir($sourcePath)) {
            throw new \ErrorException(\sprintf('Could not find path %s', $sourcePath));
        }


        // Symlink already exists
        if (\is_link($destPath)) {
            if (\realpath(\readlink($destPath)) === \realpath($sourcePath)) {
                // .. and is equal to current source-link
                return true;
            }
            \unlink($destPath);
        }

        // Create all directories up to one below the target if they don't exist
        $destDir = \dirname($destPath);
        if (!\file_exists($destDir)) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (!\mkdir($destDir, 0777, true) && !\is_dir($destDir)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destDir));
            }
        }

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
                $destPath .= '/' . \basename($source);
            }
            return $this->create($source, \substr($destPath, \strlen($this->getDestDir()) + 1));
        }

        // From now on $destPath can't be a directory, that case is already handled

        // If file exists and force is not specified, throw exception unless FORCE is set
        // existing symlinks are already handled
        if (\file_exists($destPath)) {
            if ($this->isForced()) {
                \unlink($destPath);
            } else {
                throw new \ErrorException(\sprintf('Target %s already exists and is not a symlink (set extra.files-force to override)',
                    $dest));
            }
        }

        // Create symlink
        if (false === \symlink($sourcePath, $destPath)) {
            throw new \ErrorException(\sprintf('An error occurred while creating symlink %s', $sourcePath));
        }

        // Check we where able to create the symlink
        if (false === $destPath = \readlink($destPath)) {
            throw new \ErrorException(\sprintf('Symlink %s points to target %s', $destPath, $destPath));
        }

        return true;
    }
}
