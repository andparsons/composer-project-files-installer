<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Composer;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Sozo\ProjectFiles\InstallerInterface;
use Sozo\ProjectFiles\InstallStrategy\InstallStrategyInterface;
use Sozo\ProjectFiles\ParserInterface;
use Sozo\ProjectFiles\Types\ExtraTypes;
use Sozo\ProjectFiles\Types\PackageTypes;
use Sozo\ProjectFiles\Types\StrategyTypes;

class ComposerInstaller extends \Composer\Installer\LibraryInstaller implements ComposerInstallerInterface
{
    /**
     * The base directory of the project
     *
     * @var \SplFileInfo
     */
    private $projectRootDir;

    /**
     * If set overrides existing files
     *
     * @var bool
     */
    private $isForced = false;

    /**
     * @var string
     */
    private $installStrategy = StrategyTypes::SYMLINK;

    /**
     * @var InstallerInterface
     */
    private $installer;

    /**
     * @var array Path mapping prefixes that need to be translated (i.e. to
     * use a public directory as the web server root).
     */
    private $pathMappingTranslations = [];

    /**
     * @inheritDoc
     * @throws \ErrorException
     */
    public function __construct(
        \Composer\IO\IOInterface $io,
        \Composer\Composer $composer,
        string $type = 'library'
    ) {
        parent::__construct($io, $composer, $type);
        $this->initializeVendorDir();

        $extra = $composer->getPackage()->getExtra();

        $dir = \rtrim(\trim('./'), '/\\');
        $this->projectRootDir = new \SplFileInfo($dir);
        if (!\is_dir($dir) && $io->askConfirmation(\sprintf('root dir %s missing! create now? [Y,n] ', $dir))) {
            $this->initializeRootDir();
            $io->write(\sprintf('root dir %s created', $dir));
        }

        if (!\is_dir($dir)) {
            $dir = $this->vendorDir . "/$dir";
            $this->projectRootDir = new \SplFileInfo($dir);
        }

        if (isset($extra[ExtraTypes::FILES_STRATEGY])) {
            $this->installStrategy = (string)$extra[ExtraTypes::FILES_STRATEGY];
        }

        if ($this->installStrategy !== StrategyTypes::NONE
            && ($this->projectRootDir === null || false === $this->projectRootDir->isDir())
        ) {
            $dir = $this->projectRootDir instanceof \SplFileInfo ? $this->projectRootDir->getPathname() : '';
            $io->write(\sprintf('<error>root dir "%s" is not valid</error>', $dir));
            throw new \ErrorException(\sprintf('root dir "%s" is not valid', $dir));
        }

        if (isset($extra[ExtraTypes::FILES_FORCE])) {
            $this->isForced = (bool)$extra[ExtraTypes::FILES_FORCE];
        }

        if (!empty($extra[ExtraTypes::FILES_TRANSLATIONS])) {
            $this->pathMappingTranslations = (array)$extra[ExtraTypes::FILES_TRANSLATIONS];
        }
    }

    /**
     * Create base requrements for project installation
     */
    protected function initializeRootDir(): void
    {
        if (!$this->projectRootDir->isDir()) {
            $rootPath = $this->projectRootDir->getPathname();
            $pathParts = \explode(\DIRECTORY_SEPARATOR, $rootPath);
            $baseDir = \explode(\DIRECTORY_SEPARATOR, $this->vendorDir);
            \array_pop($baseDir);
            $pathParts = \array_merge($baseDir, $pathParts);
            $directoryPath = '';
            foreach ($pathParts as $pathPart) {
                $directoryPath .= $pathPart . \DIRECTORY_SEPARATOR;
                $this->filesystem->ensureDirectoryExists($directoryPath);
            }
        }
        return;
    }

    /** @inheritDoc */
    public function setInstaller(InstallerInterface $installer): ComposerInstallerInterface
    {
        $this->installer = $installer;

        return $this;
    }

    /** @inheritDoc */
    public function supports($packageType): bool
    {
        return \array_key_exists($packageType, PackageTypes::ENUM);
    }

    /** @inheritDoc */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        parent::install($repo, $package);

        // skip marshal and apply default behavior if extra->map does not exist
        if (!$this->hasExtraMap($package)) {
            return;
        }

        $this->addPackage($package);
        return;
    }

    /**
     * Checks if package has extra map value set
     */
    protected function hasExtraMap(PackageInterface $package): bool
    {
        $packageExtra = $package->getExtra();
        if (isset($packageExtra[ExtraTypes::FILES_MAP])) {
            return true;
        }

        return false;
    }

    protected function addPackage(PackageInterface $package): void
    {
        $strategy = $this->getInstallStrategy($package);
        try {
            $strategy->setMappings($this->getParser($package)->getMappings());
        } catch (\ErrorException $e) {
            $this->io->write($e->getMessage());
        }
        $installInstance = new \Sozo\ProjectFiles\Installer\Instance();
        $installInstance->setPackageName($package->getName());
        $installInstance->setInstallStrategy($strategy);
        $this->installer->addPackage($installInstance);
        return;
    }

    /**
     * Returns the strategy class used for install
     *
     * @param PackageInterface $package
     * @param string $strategy
     * @return InstallStrategyInterface
     */
    protected function getInstallStrategy(PackageInterface $package, $strategy = null): InstallStrategyInterface
    {
        if (null === $strategy) {
            $strategy = $this->installStrategy;
        }
        $extra = $this->composer->getPackage()->getExtra();
        if (isset($extra[ExtraTypes::FILES_OVERWRITE])) {
            $moduleSpecificInstallStrategies = $this->transformArrayKeysToLowerCase($extra[ExtraTypes::FILES_OVERWRITE]);
            if (isset($moduleSpecificInstallStrategies[$package->getName()])) {
                $strategy = $moduleSpecificInstallStrategies[$package->getName()];
            }
        }
        $moduleSpecificInstallIgnores = [];
        if (isset($extra[ExtraTypes::FILES_IGNORE])) {
            $extra[ExtraTypes::FILES_IGNORE] = $this->transformArrayKeysToLowerCase($extra[ExtraTypes::FILES_IGNORE]);
            if (isset($extra[ExtraTypes::FILES_IGNORE]['*'])) {
                $moduleSpecificInstallIgnores = $extra[ExtraTypes::FILES_IGNORE]['*'];
            }
            if (isset($extra[ExtraTypes::FILES_IGNORE][$package->getName()])) {
                $moduleSpecificInstallIgnores = \array_merge(
                    $moduleSpecificInstallIgnores,
                    $extra[ExtraTypes::FILES_IGNORE][$package->getName()]
                );
            }
        }
        $targetDir = $this->getTargetDir();
        $sourceDir = $this->getSourceDir($package);
        switch ($strategy) {
            case StrategyTypes::COPY:
                $impl = new \Sozo\ProjectFiles\InstallStrategy\Copy($sourceDir, $targetDir);
                break;
            case StrategyTypes::NONE:
                $impl = new \Sozo\ProjectFiles\InstallStrategy\None($sourceDir, $targetDir);
                break;
            case StrategyTypes::SYMLINK:
            default:
                $impl = new \Sozo\ProjectFiles\InstallStrategy\Symlink($sourceDir, $targetDir);
        }
        // Inject isForced setting from extra config
        $impl->setIsForced($this->isForced);
        $impl->setIgnoredMappings($moduleSpecificInstallIgnores);
        return $impl;
    }

    protected function transformArrayKeysToLowerCase(array $array): array
    {
        $arrayNew = [];
        foreach ($array as $key => $value) {
            $arrayNew[\strtolower($key)] = $value;
        }
        return $arrayNew;
    }

    /**
     * Return the absolute target directory path for package installation
     */
    protected function getTargetDir(): string
    {
        return \realpath($this->projectRootDir->getPathname());
    }

    /**
     * Return Source dir of package
     */
    protected function getSourceDir(PackageInterface $package): string
    {
        $this->filesystem->ensureDirectoryExists($this->vendorDir);
        return $this->getInstallPath($package);
    }

    /** @inheritDoc */
    public function getInstallPath(PackageInterface $package): string
    {
        $installPath = parent::getInstallPath($package);

        // Make install path absolute. This is needed in the symlink install strategies.
        if ($installPath[0] !== \DIRECTORY_SEPARATOR && $installPath[1] !== ':') {
            $installPath = \getcwd() . \DIRECTORY_SEPARATOR . $installPath;
        }

        return $installPath;
    }

    /**
     * @throws \ErrorException
     */
    protected function getParser(PackageInterface $package): ParserInterface
    {
        $extra = $package->getExtra();
        $moduleSpecificExtra = $this->composer->getPackage()->getExtra();
        if (isset($moduleSpecificExtra[ExtraTypes::FILES_MAP_OVERWRITE])) {
            $moduleSpecificExtra = $this->transformArrayKeysToLowerCase($moduleSpecificExtra[ExtraTypes::FILES_MAP_OVERWRITE]);
            if (isset($moduleSpecificExtra[$package->getName()])) {
                $map = $moduleSpecificExtra[$package->getName()];
            }
        }
        $suffix = PackageTypes::ENUM[$package->getType()];
        if (isset($map)) {
            $parser = new \Sozo\ProjectFiles\Parser($map, $this->pathMappingTranslations, $suffix);
            return $parser;
        }

        if (isset($extra[ExtraTypes::FILES_MAP])) {
            $parser = new \Sozo\ProjectFiles\Parser($extra[ExtraTypes::FILES_MAP], $this->pathMappingTranslations,
                $suffix);
            return $parser;
        }

        throw new \ErrorException('Unable to find install strategy for module: no known mapping');
    }

    /**
     * @inheritDoc
     * @throws \ErrorException
     */
    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface $initial,
        PackageInterface $package
    ): void {
        // cleanup marshaled files if extra->map exist
        if ($this->hasExtraMap($initial)) {
            $initialStrategy = $this->getInstallStrategy($initial);
            $initialStrategy->setMappings($this->getParser($initial)->getMappings());
            try {
                $initialStrategy->clean();
            } catch (\ErrorException $e) {
                $this->io->write($e->getMessage());
            }
        }

        parent::update($repo, $initial, $package);

        // marshal files for new package version if extra->map exist
        if ($this->hasExtraMap($package)) {
            $this->addPackage($package);
        }

        return;
    }
}
