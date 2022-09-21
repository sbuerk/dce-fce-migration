<?php

declare(strict_types=1);

namespace SB\DceFceMigration\MigrationMapping;

use SB\DceFceMigration\AbstractMapping;
use SB\DceFceMigration\MigrationHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Class MappingCollectedFalToFal
 */
class MappingCollectedFalToFal extends AbstractMapping
{
    public const TYPE = 'collectedFalToFal';

    /**
     * @param string $src <type>.<path>   Types: row,flex,fal
     * @param string $dst <type>.<path>   Types: row,flex
     * @param string $srcTable
     * @param string $dstTable
     *
     * @return string[]
     */
    public static function create(
        string $src,
        string $dst,
        string $srcTable = 'tt_content',
        string $dstTable = 'tt_content'
    ): array {
        return [
            'mapping_type' => self::TYPE,

            'src' => $src,
            'dst' => $dst,

            'srcTable' => $srcTable,
            'dstTable' => $dstTable,
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
        $srcTable = $options['srcTable'] ?? 'tt_content';
        $dstTable = $options['dstTable'] ?? 'tt_content';

        if (empty($srcType)) {
            $io->writeln('[E] CollectedFalToFal Mapping Source Type not defined (row or flex)');
            return;
        }
        if (empty($dstType)) {
            $io->writeln('[E] CollectedFalToFal Mapping Destination Type not defined (row or flex)');
            return;
        }

        if (empty($srcPath)) {
            $io->writeln('[E] CollectedFalToFal Mapping Source Path not defined');
            return;
        }
        if (empty($dstPath)) {
            $io->writeln('[E] CollectedFalToFal Mapping Destination Path not defined');
            return;
        }
        if (!ArrayUtility::isValidPath($src[$srcType], $srcPath, '/')) {
            $io->writeln('[E] CollectedFalToFal Mapping Source Path not existing.');
            return;
        }

        $dstFieldName = (string)self::getLastPathElement((string)self::getLastPathElement(($dstType === 'flex' ? self::removeLastPathElement(
            $dstPath,
            '/'
        ) : $dstPath), '/'), '.');
        $srcValue = ArrayUtility::getValueByPath($src[$srcType], $srcPath, '/');
        if (is_array($srcValue) && !empty($srcValue)) {
            /** @var FileReference[] $srcValue */
            foreach ($srcValue as $srcFileReference) {
                $isFileReference = $srcFileReference instanceof FileReference;
                /** @var File $srcFile */
                $srcFile = $isFileReference ? $srcFileReference->getOriginalFile() : $srcFileReference;
                switch ($dstType) {
                    case 'row':
                        $additionalData = [
                            'title'             => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('title')      : ''),
                            'description'       => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('description'): ''),
                            'alternative'       => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('alternative'): ''),
                            'link'              => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('link')       : ''),
                        ];
                        if (array_key_exists('showinpreview', $GLOBALS['TCA']['sys_file_reference']['columns'] ?? [])) {
                            $additionalData['showinpreview'] = $isFileReference ? $srcFileReference->getReferenceProperty('showinpreview') : $srcFile->getProperty('showinpreview');
                        }
                        $migrationHelper->addRecordFalImage(
                            $item,
                            $dstTable,
                            $item['srcUid'],
                            $item['srcPid'],
                            $dstFieldName,
                            $srcFile,
                            $additionalData,
                            $dst
                        );
                        break;

                    case 'flex':
                        $additionalData = [
                            'title'             => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('title')      : ''),
                            'description'       => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('description'): ''),
                            'alternative'       => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('alternative'): ''),
                            'link'              => static::nullIfEmptyString($isFileReference ? $srcFileReference->getReferenceProperty('link')       : ''),
                        ];
                        if (array_key_exists('showinpreview', $GLOBALS['TCA']['sys_file_reference']['columns'] ?? [])) {
                            $additionalData['showinpreview'] = $isFileReference ? $srcFileReference->getReferenceProperty('showinpreview') : $srcFile->getProperty('showinpreview');
                        }
                        $migrationHelper->addFlexFalImage(
                            $item,
                            $dstTable,
                            $item['srcUid'],
                            $item['srcPid'],
                            $dstFieldName,
                            $srcFile,
                            $additionalData,
                            $dst['flex'],
                            $dstPath
                        );
                        break;

                    default:
                        // noop
                }
            }
        }
    }
}
