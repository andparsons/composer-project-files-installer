<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Types;

class PackageTypes
{
    /**
     * Package Types supported by Installer
     * @var array
     */
    public const ENUM = [
        'sozo-deploy-files' => '/config/deploy/',
        'sozo-build-files' => '/config/build/',
        'sozo-project-files' => './'
    ];
}
