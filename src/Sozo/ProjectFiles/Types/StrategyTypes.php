<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Types;

class StrategyTypes
{
    /**
     * Package Types supported by Installer
     * @var array
     */
    public const ENUM = [
        self::COPY,
        self::NONE,
        self::SYMLINK,
    ];

    public const COPY = 'copy';
    public const NONE = 'none';
    public const SYMLINK = 'symlink';
}
