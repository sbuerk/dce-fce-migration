<?php

declare(strict_types=1);

namespace SB\DceFceMigration;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AbstractMapping
 */
abstract class AbstractMapping
{
    /** @var MigrationHelper */
    protected $migrationHelper;

    abstract public static function process(
        SymfonyStyle $io,
        MigrationHelper $migrationHelper,
        array $options,
        array &$src,
        array &$dst,
        array &$item
    ): void;

    /**
     * @param string $fullPath
     * @return array|string[]|null[]
     */
    public static function splitFieldTypePath(string $fullPath): array
    {
        $parts = explode('/', $fullPath, 2);
        if (count($parts) === 2) {
            return [
                $parts[0],
                $parts[1],
            ];
        }

        return [null, null];
    }

    public static function getLastPathElement(string $fullPath, string $delimiter = '/'): ?string
    {
        if (empty($fullPath)) {
            return null;
        }

        $parts = explode('/', $fullPath);
        if ($parts) {
            return $parts[array_key_last($parts)];
        }

        return null;
    }

    public static function removeLastPathElement(string $fullPath, string $delimiter = '/'): ?string
    {
        if (empty($fullPath)) {
            return null;
        }

        $parts = explode('/', $fullPath);
        unset($parts[array_key_last($parts)]);
        if ($parts) {
            return $parts[array_key_last($parts)];
        }

        return null;
    }

    public static function nullIfEmptyString(?string $value): ?string
    {
        return $value;
    }
}
