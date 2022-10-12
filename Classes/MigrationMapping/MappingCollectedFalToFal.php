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
     * @param array<int, array<string, string|int|float|bool|null>> $falImagesAdditionalData
     *
     * @return string[]
     */
    public static function create(
        string $src,
        string $dst,
        string $srcTable = 'tt_content',
        string $dstTable = 'tt_content',
        array $falImagesAdditionalData = []
    ): array {
        return [
            'mapping_type' => self::TYPE,

            'src' => $src,
            'dst' => $dst,

            'srcTable' => $srcTable,
            'dstTable' => $dstTable,

            'falImagesAdditionalData' => $falImagesAdditionalData ?? [],
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

        $falImagesAdditionalData = $options['falImagesAdditionalData'] ?? [];
        if (!is_array($falImagesAdditionalData)) {
            $falImagesAdditionalData = [];
        }

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
            foreach ($srcValue as $index => $srcFileReference) {
                $index = (int)$index;
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
                        if (array_key_exists($index, $falImagesAdditionalData) && is_array($falImagesAdditionalData[$index] ?? null) && $falImagesAdditionalData[$index] !== []) {
                            foreach ($falImagesAdditionalData[$index] as $falImageAdditionalDataKey => $falImageAdditionalDataValue) {
                                if (!array_key_exists($falImageAdditionalDataKey, $GLOBALS['TCA']['sys_file_reference']['columns'] ?? [])) {
                                    continue;
                                }
                                $additionalData[$falImageAdditionalDataKey] = $falImageAdditionalDataValue;
                            }
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
                        if (array_key_exists($index, $falImagesAdditionalData) && is_array($falImagesAdditionalData[$index] ?? null) && $falImagesAdditionalData[$index] !== []) {
                            foreach ($falImagesAdditionalData[$index] as $falImageAdditionalDataKey => $falImageAdditionalDataValue) {
                                if (!array_key_exists($falImageAdditionalDataKey, $GLOBALS['TCA']['sys_file_reference']['columns'] ?? [])) {
                                    continue;
                                }
                                $additionalData[$falImageAdditionalDataKey] = $falImageAdditionalDataValue;
                            }
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
