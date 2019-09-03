<?php declare(strict_types=1);

namespace Sozo\ProjectFiles\Types;

class ExtraTypes
{
    /**
     * Package Types supported by Installer
     * @var array
     */
    public const ENUM = [
        self::FILES_FORCE,
        self::FILES_IGNORE,
        self::FILES_STRATEGY,
        self::FILES_MAP_OVERWRITE,
        self::FILES_OVERWRITE,
        self::FILES_PRIORITY,
        self::FILES_MAP,
        self::FILES_TRANSLATIONS,
    ];

    public const FILES_FORCE = 'files-force';
    public const FILES_IGNORE = 'files-ignore';
    public const FILES_STRATEGY = 'files-install-strategy';
    public const FILES_MAP_OVERWRITE = 'files-map-overwrite';
    public const FILES_OVERWRITE = 'files-overwrite';
    public const FILES_MAP = 'map';
    public const FILES_TRANSLATIONS = 'path-mapping-translations';
    public const FILES_PRIORITY = 'files-sort-priority';
}
