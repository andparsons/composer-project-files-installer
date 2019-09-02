<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\InstallStrategy;

class Copy extends InstallStrategyAbstract
{
    /**
     * copy files
     *
     * @param string $source
     * @param string $dest
     * @return bool
     * @throws \ErrorException
     */
    protected function createDelegate(string $source, string $dest): bool
    {
        $filesystem = new \Composer\Util\Filesystem();
        [$mapSource, $mapDest] = $this->getCurrentMapping();
        $mapSource = $this->removeTrailingSlash($mapSource);
        $mapDest = $this->removeTrailingSlash($mapDest);
        $cleanDest = $this->removeTrailingSlash($dest);

        $sourcePath = $this->getSourceDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . \DIRECTORY_SEPARATOR . $this->removeTrailingSlash($dest);


        // Create all directories up to one below the target if they don't exist
        $destDir = \dirname($destPath);
        $filesystem->ensureDirectoryExists($destDir);

        // first iteration through, we need to update the mappings to correctly handle mismatch globs
        if ($mapSource === $this->removeTrailingSlash($source)
            && $mapDest === $this->removeTrailingSlash($dest)
            && \basename($sourcePath) !== \basename($destPath)
        ) {
            $this->setCurrentMapping([$mapSource, $mapDest . \DIRECTORY_SEPARATOR . \basename($source)]);
            $cleanDest .= \DIRECTORY_SEPARATOR . \basename($source);
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
                    $childSource = $this->removeTrailingSlash($source) . \DIRECTORY_SEPARATOR . $item;
                    $this->create($childSource, \substr($destPath, \strlen($this->getDestDir()) + 1));
                }
                return true;
            }

            $destPath = $this->removeTrailingSlash($destPath) . \DIRECTORY_SEPARATOR . \basename($source);
            return $this->create($source, \substr($destPath, \strlen($this->getDestDir()) + 1));
        }


        if ($this->isForced()) {
            $filesystem->remove($destPath);
        }

        return $filesystem->copy($sourcePath, $destPath);
    }
}
