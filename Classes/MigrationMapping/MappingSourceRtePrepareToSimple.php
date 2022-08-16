<?php

namespace SB\DceFceMigration\MigrationMapping;

use SB\DceFceMigration\AbstractMapping;
use SB\DceFceMigration\MigrationHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MappingSourceRtePrepareToSimple
 */
class MappingSourceRtePrepareToSimple extends AbstractMapping
{
    public const TYPE = 'rtePrepareToSimple';

    /**
     * @param string $src <type>.<path>   Types: row,flex
     * @param string $dst <type>.<path>   Types: row,flex
     *
     * @return string[]
     */
    public static function create(
        string $src,
        string $dst,
        string $fieldname = 'pi_flexform',
        string $richtextConfiguration = 'default'
    ) {
        return [
            'mapping_type' => self::TYPE,
            'src' => $src,
            'dst' => $dst,
            'fieldname' => $fieldname,
            'richtextConfiguration' => $richtextConfiguration,
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

        // @TODO Test Rte Transform
        $srcValue = ArrayUtility::getValueByPath($src[$srcType], $srcPath, '/');
        $srcValue = self::rteTransform(
            $item['srcTable'],
            $options['fieldname'],
            $item['srcPid'],
            $options['richtextConfiguration'] ?? 'default',
            (string)$srcValue
        );
        $dst[$dstType] = ArrayUtility::setValueByPath($dst[$dstType], $dstPath, $srcValue, '/');

        $b = 1;
    }

    protected static function rteTransform(
        string $tablename,
        string $fieldname,
        int $pid,
        string $richtextConfigurationName,
        string $value
    ) {
        $b = 1;

        $richtextConfigurationProvider = GeneralUtility::makeInstance(Richtext::class);
        $richtextConfiguration = $richtextConfigurationProvider->getConfiguration(
            $tablename,
            $fieldname,
            $pid,
            '',
            json_decode(
                '{"type":"text","rows":"5","cols":"30","eval":"trim","enableRichtext":true,"richtextConfiguration":"default"}',
                true
            )
        );

        /** @var RteHtmlParser $parseHTML */
        $parseHTML = GeneralUtility::makeInstance(RteHtmlParser::class);
        $parseHTML->init($tablename . ':' . $fieldname, $pid);
        $b = 1;
        $transformedValue = $parseHTML->RTE_transform(
            $value,
            [],
            'rte',
            $richtextConfiguration
        );
        $b = 1;
//        $transformedValue = $parseHTML->RTE_transform(
//            $transformedValue,
//            [],
//            'db',
//            $richtextConfiguration
//        );

        $b = 1;
        return $transformedValue;
    }
}
