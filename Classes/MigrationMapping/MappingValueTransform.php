<?php

declare(strict_types=1);

namespace SB\DceFceMigration\MigrationMapping;

use SB\DceFceMigration\AbstractMapping;
use SB\DceFceMigration\MigrationHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Class MappingValueTransform
 */
class MappingValueTransform extends AbstractMapping
{
    public const TYPE = 'valueTransform';

    /**
     * @param string $src
     * @param string $dst
     * @param array $map
     * @param mixed $default
     * @return array
     */
    public static function create(string $src, string $dst, array $map, $default = ''): array
    {
        return [
            'mapping_type' => self::TYPE,
            'src' => $src,
            'dst' => $dst,
            'map' => $map,
            'default' => $default,
        ];
    }

    public static function process(
        SymfonyStyle $io,
        MigrationHelper $migrationHelper,
        array $options,
        array &$src,
        array &$dst,
        array &$item
    ): void {
        $map = $options['map'];
        [$srcType, $srcPath] = self::splitFieldTypePath((string)($options['src'] ?? ''));
        [$dstType, $dstPath] = self::splitFieldTypePath((string)($options['dst'] ?? ''));

        if (empty($srcType)) {
            $io->writeln('[E] ValueTransform Mapping Source Type not defined (row or flex)');
            return;
        }
        if (empty($dstType)) {
            $io->writeln('[E] ValueTransform Mapping Destination Type not defined (row or flex)');
            return;
        }

        if (empty($srcPath)) {
            $io->writeln('[E] ValueTransform Mapping Source Path not defined');
            return;
        }
        if (empty($dstPath)) {
            $io->writeln('[E] ValueTransform Mapping Destination Path not defined');
            return;
        }

        if (!ArrayUtility::isValidPath($src[$srcType], $srcPath, '/')) {
            $io->writeln('[E] ValueTransform Mapping Source Path not existing.');
            return;
        }

        $srcValue = ArrayUtility::getValueByPath($src[$srcType], $srcPath, '/');
        $defValue = $options['default'] ?? '';
        $dstValue = $map[$srcValue] ?? $defValue;

        $dst[$dstType] = ArrayUtility::setValueByPath($dst[$dstType], $dstPath, $dstValue, '/');
    }
}
