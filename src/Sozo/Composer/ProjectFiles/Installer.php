<?php declare(strict_types=1);

namespace Sozo\Composer\ProjectFiles;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends \Composer\Installer\LibraryInstaller
{
    /**
     * The base directory of the project
     *
     * @var \SplFileInfo
     */
    protected $projectRootDir;

    /**
     * If set overrides existing files
     *
     * @var bool
     */
    protected $isForced = false;

    /**
     * @var string
     */
    protected $deployStrategy = 'symlink';

    /**
     * @var DeployManager
     */
    protected $deployManager;

    /**
     * @var array Path mapping prefixes that need to be translated (i.e. to
     * use a public directory as the web server root).
     */
    protected $pathMappingTranslations = [];

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

        if (isset($extra['files-deploystrategy'])) {
            $this->deployStrategy = (string)$extra['files-deploystrategy'];
        }

        if ($this->deployStrategy !== 'none'
            && ($this->projectRootDir === null || false === $this->projectRootDir->isDir())
        ) {
            $dir = $this->projectRootDir instanceof \SplFileInfo ? $this->projectRootDir->getPathname() : '';
            $io->write(\sprintf('<error>root dir "%s" is not valid</error>', $dir));
            throw new \ErrorException(\sprintf('root dir "%s" is not valid', $dir));
        }

        if (isset($extra['files-force'])) {
            $this->isForced = (bool)$extra['files-force'];
        }

        if (isset($extra['files-deploystrategy'])) {
            $this->setDeployStrategy((string)$extra['files-deploystrategy']);
        }

        if (!empty($extra['path-mapping-translations'])) {
            $this->pathMappingTranslations = (array)$extra['path-mapping-translations'];
        }
    }

    /**
     * Create base requrements for project installation
     */
    protected function initializeRootDir(): void
    {
        if (!$this->projectRootDir->isDir()) {
            $rootPath = $this->projectRootDir->getPathname();
            $pathParts = explode(DIRECTORY_SEPARATOR, $rootPath);
            $baseDir = explode(DIRECTORY_SEPARATOR, $this->vendorDir);
            array_pop($baseDir);
            $pathParts = array_merge($baseDir, $pathParts);
            $directoryPath = '';
            foreach ($pathParts as $pathPart) {
                $directoryPath .=  $pathPart . DIRECTORY_SEPARATOR;
                $this->filesystem->ensureDirectoryExists($directoryPath);
            }
        }
        return;
    }

    public function getDeployManager(): DeployManager
    {
        return $this->deployManager;
    }

    public function setDeployManager(DeployManager $deployManager): self
    {
        $this->deployManager = $deployManager;

        return $this;
    }

    /** @inheritDoc */
    public function supports($packageType): bool
    {
        return \array_key_exists($packageType, PackageTypes::$packageTypes);
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
    private function hasExtraMap(PackageInterface $package): bool
    {
        $packageExtra = $package->getExtra();
        if (isset($packageExtra['map'])) {
            return true;
        }

        return false;
    }

    private function addPackage(PackageInterface $package): void
    {
        $strategy = $this->getDeployStrategy($package);
        try {
            $strategy->setMappings($this->getParser($package)->getMappings());
        } catch (\ErrorException $e) {
            $this->io->write($e->getMessage());
        }
        $deployManagerEntry = new \Sozo\Composer\ProjectFiles\Deploy\Manager\Entry();
        $deployManagerEntry->setPackageName($package->getName());
        $deployManagerEntry->setDeployStrategy($strategy);
        $this->deployManager->addPackage($deployManagerEntry);
        return;
    }

    /**
     * Returns the strategy class used for deployment
     *
     * @param PackageInterface $package
     * @param string $strategy
     * @return \Sozo\Composer\ProjectFiles\DeployStrategy\DeployStrategyAbstract
     */
    public function getDeployStrategy(PackageInterface $package, $strategy = null)
    {
        if (null === $strategy) {
            $strategy = $this->deployStrategy;
        }
        $extra = $this->composer->getPackage()->getExtra();
        if (isset($extra['files-overwrite'])) {
            $moduleSpecificDeployStrategies = $this->transformArrayKeysToLowerCase($extra['files-overwrite']);
            if (isset($moduleSpecificDeployStrategies[$package->getName()])) {
                $strategy = $moduleSpecificDeployStrategies[$package->getName()];
            }
        }
        $moduleSpecificDeployIgnores = [];
        if (isset($extra['files-ignore'])) {
            $extra['files-ignore'] = $this->transformArrayKeysToLowerCase($extra['files-ignore']);
            if (isset($extra['files-ignore']['*'])) {
                $moduleSpecificDeployIgnores = $extra['files-ignore']['*'];
            }
            if (isset($extra['files-ignore'][$package->getName()])) {
                $moduleSpecificDeployIgnores = \array_merge(
                    $moduleSpecificDeployIgnores,
                    $extra['files-ignore'][$package->getName()]
                );
            }
        }
        $targetDir = $this->getTargetDir();
        $sourceDir = $this->getSourceDir($package);
        switch ($strategy) {
            case 'copy':
                $impl = new \Sozo\Composer\ProjectFiles\DeployStrategy\Copy($sourceDir, $targetDir);
                break;
            case 'none':
                $impl = new \Sozo\Composer\ProjectFiles\DeployStrategy\None($sourceDir, $targetDir);
                break;
            case 'symlink':
            default:
                $impl = new \Sozo\Composer\ProjectFiles\DeployStrategy\Symlink($sourceDir, $targetDir);
        }
        // Inject isForced setting from extra config
        $impl->setIsForced($this->isForced);
        $impl->setIgnoredMappings($moduleSpecificDeployIgnores);
        return $impl;
    }

    public function setDeployStrategy(string $strategy): self
    {
        $this->deployStrategy = $strategy;

        return $this;
    }

    public function transformArrayKeysToLowerCase(array $array): array
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
    public function getTargetDir(): string
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

        // Make install path absolute. This is needed in the symlink deploy strategies.
        if ($installPath[0] !== \DIRECTORY_SEPARATOR && $installPath[1] !== ':') {
            $installPath = \getcwd().'/'.$installPath;
        }

        return $installPath;
    }

    /**
     * @throws \ErrorException
     */
    public function getParser(PackageInterface $package): Parser
    {
        $extra = $package->getExtra();
        $moduleSpecificMap = $this->composer->getPackage()->getExtra();
        if (isset($moduleSpecificMap['files-map-overwrite'])) {
            $moduleSpecificMap = $this->transformArrayKeysToLowerCase($moduleSpecificMap['files-map-overwrite']);
            if (isset($moduleSpecificMap[$package->getName()])) {
                $map = $moduleSpecificMap[$package->getName()];
            }
        }
        $suffix = PackageTypes::$packageTypes[$package->getType()];
        if (isset($map)) {
            $parser = new MapParser($map, $this->pathMappingTranslations, $suffix);
            return $parser;
        }

        if (isset($extra['map'])) {
            $parser = new MapParser($extra['map'], $this->pathMappingTranslations, $suffix);
            return $parser;
        }

        throw new \ErrorException('Unable to find deploy strategy for module: no known mapping');
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
            $initialStrategy = $this->getDeployStrategy($initial);
            $initialStrategy->setMappings($this->getParser($initial)->getMappings());
            try {
                $initialStrategy->clean();
            } catch (\ErrorException $e) {
                if ($this->io->isDebug()) {
                    $this->io->write($e->getMessage());
                }
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
