<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\DeployStrategy;

class Copy extends DeployStrategyAbstract
{
    /**
     * copy files
     *
     * @param string $source
     * @param string $dest
     * @return bool
     * @throws \ErrorException
     */
    public function createDelegate(string $source, string $dest): bool
    {
        [$mapSource, $mapDest] = $this->getCurrentMapping();
        $mapSource = $this->removeTrailingSlash($mapSource);
        $mapDest = $this->removeTrailingSlash($mapDest);
        $cleanDest = $this->removeTrailingSlash($dest);

        $sourcePath = $this->getSourceDir() . '/' . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . '/' . $this->removeTrailingSlash($dest);


        // Create all directories up to one below the target if they don't exist
        $destDir = \dirname($destPath);
        if (!\file_exists($destDir)) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if (!\mkdir($destDir, 0777, true) && !\is_dir($destDir)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destDir));
            }
        }

        // first iteration through, we need to update the mappings to correctly handle mismatch globs
        if ($mapSource === $this->removeTrailingSlash($source)
            && $mapDest === $this->removeTrailingSlash($dest)
            && \basename($sourcePath) !== \basename($destPath)
        ) {
            $this->setCurrentMapping([$mapSource, $mapDest . '/' . \basename($source)]);
            $cleanDest .= '/' . \basename($source);
        }

        if (\file_exists($destPath) && \is_dir($destPath)) {
            $mapSource = \rtrim($mapSource, '*');
            $mapSourceLen = empty($mapSource) ? 0 : \strlen($mapSource) + 1;
            if (\strcmp(\substr($cleanDest, \strlen($mapDest) + 1), \substr($source, $mapSourceLen)) === 0) {
                // copy each child of $sourcePath into $destPath
                foreach (new \DirectoryIterator($sourcePath) as $item) {
                    $item = (string)$item;
                    if (!\strcmp($item, '.') || !\strcmp($item, '..')) {
                        continue;
                    }
                    $childSource = $this->removeTrailingSlash($source) . '/' . $item;
                    $this->create($childSource, \substr($destPath, \strlen($this->getDestDir()) + 1));
                }
                return true;
            }

            $destPath = $this->removeTrailingSlash($destPath) . '/' . \basename($source);
            return $this->create($source, \substr($destPath, \strlen($this->getDestDir()) + 1));
        }

        // From now on $destPath can't be a directory, that case is already handled

        // If file exists and force is not specified, throw exception unless FORCE is set
        if (\file_exists($destPath)) {
            if ($this->isForced()) {
                \unlink($destPath);
            } else {
                throw new \ErrorException(\sprintf('Target %s already exists (set extra.files-force to override)',
                    $dest));
            }
        }

        // File to file
        if (!\is_dir($sourcePath)) {
            if (\is_dir($destPath)) {
                $destPath .= '/' . \basename($sourcePath);
            }
            return \copy($sourcePath, $destPath);
        }

        // Copy dir to dir
        // First create destination folder if it doesn't exist
        if (\file_exists($destPath)) {
            $destPath .= '/' . \basename($sourcePath);
        }
        if (!\mkdir($destPath, 0777, true) && !\is_dir($destPath)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destPath));
        }
        /** @var \RecursiveDirectoryIterator $iterator */
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourcePath),
            \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $subDestPath = $destPath . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!\file_exists($subDestPath)) {
                    /** @noinspection NestedPositiveIfStatementsInspection */
                    if (!\mkdir($subDestPath, 0777, true) && !\is_dir($subDestPath)) {
                        throw new \RuntimeException(\sprintf('Directory "%s" was not created', $subDestPath));
                    }
                }
            } else {
                \copy($item->getPathname(), $subDestPath);
            }
            if (!\is_readable($subDestPath)) {
                throw new \ErrorException(\sprintf('Could not create %s', $subDestPath));
            }
        }

        return true;
    }
}
