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

        // Windows doesn't allow relative symlinks
        if (\stripos(\PHP_OS, 'WIN') !== 0) {
            $sourcePath = $this->getRelativePath($destPath, $sourcePath);
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

    /**
     * Returns the relative path from $from to $to
     * This is utility method for symlink creation.
     */
    public function getRelativePath(string $from, string $to): string
    {
        $from = \str_replace(['/./', '//', '\\'], '/', $from);
        $to = \str_replace(['/./', '//', '\\'], '/', $to);

        if (\is_file($from)) {
            $from = \dirname($from);
        } else {
            $from = \rtrim($from, '/');
        }

        $dir = \explode('/', $from);
        $file = \explode('/', $to);

        while ($file && $dir && ($dir[0] === $file[0])) {
            \array_shift($file);
            \array_shift($dir);
        }

        return \str_repeat('../', \count($dir)) . \implode('/', $file);
    }
}
