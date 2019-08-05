<?php declare(strict_types=1);

namespace Sozo\ProjectFiles;

class PackageTypes
{
    /**
     * Package Types supported by Installer
     * @var array
     */
    public static $packageTypes = [
        'sozo-deploy-files' => '/config/deploy/',
        'sozo-build-files' => '/config/build/',
        'sozo-project-files' => './'
    ];
}