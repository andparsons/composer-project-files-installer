<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Composer;

use Sozo\ProjectFiles\InstallerInterface;

interface ComposerInstallerInterface extends \Composer\Installer\InstallerInterface
{
    public function setInstaller(InstallerInterface $installer): self;
}
