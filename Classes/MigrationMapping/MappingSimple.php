<?php

declare(strict_types=1);

namespace SB\DceFceMigration\MigrationMapping;

use SB\DceFceMigration\AbstractMapping;
use SB\DceFceMigration\MigrationHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Class MappingSimple
 */
class MappingSimple extends AbstractMapping
{
    public const TYPE = 'simple';

    /**
     * @param string $src <type>.<path>   Types: row,flex
     * @param string $dst <type>.<path>   Types: row,flex
     *
     * @return string[]
     */
    public static function create(
        string $src,
        string $dst
    ) {
        return [
            'mapping_type' => self::TYPE,
            'src' => $src,
            'dst' => $dst,
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
        [$srcType, $srcPath] = self::splitFieldTypePath((string)($options['src'] ?? ''));
        [$dstType, $dstPath] = self::splitFieldTypePath((string)($options['dst'] ?? ''));

        if (empty($srcType)) {
            $io->writeln('[E] Simple Mapping Source Type not defined (row or flex)');
            return;
        }
        if (empty($dstType)) {
            $io->writeln('[E] Simple Mapping Destination Type not defined (row or flex)');
            return;
        }

        if (empty($srcPath)) {
            $io->writeln('[E] Simple Mapping Source Path not defined');
            return;
        }
        if (empty($dstPath)) {
            $io->writeln('[E] Simple Mapping Destination Path not defined');
            return;
        }

        if (!ArrayUtility::isValidPath($src[$srcType] ?? [], $srcPath, '/')) {
            $io->writeln('[E] Simple Mapping Source Path not existing.');
            return;
        }

        $srcValue = ArrayUtility::getValueByPath($src[$srcType], $srcPath, '/');
        $dst[$dstType] = ArrayUtility::setValueByPath($dst[$dstType], $dstPath, $srcValue, '/');

        $b = 1;
    }
}
