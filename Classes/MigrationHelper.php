<?php

declare(strict_types=1);

namespace SB\DceFceMigration;

use Exception;
use PDO;
use SB\DceFceMigration\MigrationMapping\MappingCollectedFalToFal;
use SB\DceFceMigration\MigrationMapping\MappingSimple;
use SB\DceFceMigration\MigrationMapping\MappingSourceRtePrepareToSimple;
use SB\DceFceMigration\MigrationMapping\MappingValueTransform;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Class MigrationHelper
 */
final class MigrationHelper implements SingletonInterface
{
    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var SymfonyStyle */
    protected $style;

    /** @var ConnectionPool */
    protected $connectionPool;

    /** @var FlexFormTools */
    protected $flexFormTools;

    /** @var FlexFormService */
    protected $flexFormService;

    /**
     * @var string[]
     */
    protected $mappings = [
        MappingSimple::TYPE => MappingSimple::class,
        MappingValueTransform::TYPE => MappingValueTransform::class,
        MappingCollectedFalToFal::TYPE => MappingCollectedFalToFal::class,
        MappingSourceRtePrepareToSimple::TYPE => MappingSourceRtePrepareToSimple::class,
    ];

    /**
     * MigrationHelper constructor.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param SymfonyStyle $style
     * @param ConnectionPool $connectionPool
     * @param FlexFormTools $flexFormTools
     * @param FlexFormService $flexFormService
     */
    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $style,
        ConnectionPool $connectionPool,
        FlexFormTools $flexFormTools,
        FlexFormService $flexFormService
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->style = $style;
        $this->connectionPool = $connectionPool;
        $this->flexFormTools = $flexFormTools;
        $this->flexFormService = $flexFormService;
    }

    /**
     * Parses the flexForm content and converts it to an array
     * The resulting array will be multi-dimensional, as a value "bla.blubb"
     * results in two levels, and a value "bla.blubb.bla" results in three levels.
     *
     * Note: multi-language flexForms are not supported yet
     *
     * @param string $flexFormContent flexForm xml string
     * @param string $languagePointer language pointer used in the flexForm
     * @param string $valuePointer value pointer used in the flexForm
     * @return array the processed array
     */
    public function convertFlexFormContentToArray($flexFormContent, $languagePointer = 'lDEF', $valuePointer = 'vDEF')
    {
        return $this->flexFormService->convertFlexFormContentToArray(
            $flexFormContent,
            $languagePointer,
            $valuePointer
        );
    }

    /**
     * @param array $array
     * @param bool $addPrologue
     *
     * @return string
     */
    public function flexArray2Xml($array, $addPrologue = false)
    {
        return $this->flexFormTools->flexArray2Xml(
            $array,
            $addPrologue
        );
    }

    /**
     * Check a specific record on all TCA columns if they are FlexForms and if the FlexForm values
     * don't match to the newly defined ones.
     *
     * @param string $tableName Table name
     * @param int $uid UID of record in processing
     * @param array $dirtyFlexFormFields the existing FlexForm fields
     * @return array the updated list of dirty FlexForm fields
     */
    protected function compareAllFlexFormsInRecord(string $tableName, int $uid, array $dirtyFlexFormFields = []): array
    {
        $flexObj = GeneralUtility::makeInstance(FlexFormTools::class);
        foreach ($GLOBALS['TCA'][$tableName]['columns'] as $columnName => $columnConfiguration) {
            if ($columnConfiguration['config']['type'] === 'flex') {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
                $queryBuilder->getRestrictions()->removeAll();

                $fullRecord = $queryBuilder->select('*')
                    ->from($tableName)
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
                    )
                    ->execute()
                    ->fetch();

                if ($fullRecord[$columnName]) {
                    // Clean XML and check against the record fetched from the database
                    $newXML = $flexObj->cleanFlexFormXML($tableName, $columnName, $fullRecord);
                    if (md5($fullRecord[$columnName]) !== md5($newXML)) {
                        $dirtyFlexFormFields[$tableName . ':' . $uid . ':' . $columnName] = $fullRecord;
                    }
                }
            }
        }
        return $dirtyFlexFormFields;
    }

    /**
     * Actually cleans the database record fields with a new FlexForm as chosen currently for this record
     *
     * @param array $records
     * @param bool $dryRun
     */
    protected function cleanFlexFormRecords(array $records, bool $dryRun)
    {
        $io = $this->style;
        $flexObj = GeneralUtility::makeInstance(FlexFormTools::class);

        // Set up the data handler instance
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->dontProcessTransformations = true;
        $dataHandler->bypassWorkspaceRestrictions = true;
        $dataHandler->bypassFileHandling = true;
        // Setting this option allows to also update deleted records (or records on deleted pages) within DataHandler
        $dataHandler->bypassAccessCheckForRecords = true;

        // Loop through all tables and their records
        foreach ($records as $recordIdentifier => $fullRecord) {
            [$table, $uid, $field] = explode(':', $recordIdentifier);
            if ($io->isVerbose()) {
                $io->writeln('Cleaning FlexForm XML in "' . $recordIdentifier . '"');
            }
            if (!$dryRun) {
                // Clean XML now
                $data = [];
                if ($fullRecord[$field]) {
                    $data[$table][$uid][$field] = $flexObj->cleanFlexFormXML($table, $field, $fullRecord);
                } else {
                    $io->note('The field "' . $field . '" in record "' . $table . ':' . $uid . '" was not found.');
                    continue;
                }
                $dataHandler->start($data, []);
                $dataHandler->process_datamap();
                // Return errors if any:
                if (!empty($dataHandler->errorLog)) {
                    $errorMessage = array_merge(['DataHandler reported an error'], $dataHandler->errorLog);
                    $io->error($errorMessage);
                } elseif (!$io->isQuiet()) {
                    $io->writeln('Updated FlexForm in record "' . $table . ':' . $uid . '".');
                }
            }
        }
    }

    public function doMapping(
        array $mappings,
        array &$src,
        array &$dst,
        array &$item
    ) {
        foreach ($mappings as $options) {
            if (isset($this->mappings[$options['mapping_type']]) && class_exists($this->mappings[$options['mapping_type']])) {
                /** @var AbstractMapping $mappingClass */
                $mappingClass = $this->mappings[$options['mapping_type']];
                $mappingClass::process(
                    $this->style,
                    $this,
                    $options,
                    $src,
                    $dst,
                    $item
                );
            } else {
                $this->style->writeln('[E] Mapping type ' . (string)($options['mapping_type'] ?? '') . ' not defined for ' . static::class);
            }
        }
    }

    public function getFalImages(
        string $table,
        string $field,
        int $recordUid,
        array $additionalWheres = null
    ): ?array {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $wheres = [
            $queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter($table)
            ),
            $queryBuilder->expr()->eq(
                'fieldname',
                $queryBuilder->createNamedParameter($field)
            ),
            $queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
            ),
        ];
        if ($additionalWheres) {
            foreach ($additionalWheres as $aWhere) {
                $t = $aWhere['type'] ?? 'eq';
                $v = $aWhere['value'] ?? null;
                $f = $aWhere['field'] ?? null;

                if ($f === null || $v === null) {
                    continue;
                }
                switch ($t) {
                    case 'eq':
                        $wheres[] = $queryBuilder->expr()->eq(
                            $f,
                            $queryBuilder->createNamedParameter($v)
                        );
                        break;
                    default:
                }
            }
        }

        $queryBuilder
            ->select('uid')
            ->from('sys_file_reference')
            ->where(...$wheres)
            ->orderBy('sorting_foreign', 'ASC');
        $rows = $this->getRowsFromQueryBuilder($queryBuilder, 'uid');

        /** @var FileRepository $fileRepository */
        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        $result = [];
        foreach ($rows as $referenceUid) {
            $result[] = $fileRepository->findFileReferenceByUid((int)$referenceUid['uid']);
        }

        return empty($result) ? null : $result;
    }

    /**
     * @param array $item
     * @param string $recTable
     * @param int $recUid
     * @param int $recPid
     * @param string $imageField
     * @param File $fileObject
     * @param array|null $additionalDataForReference
     */
    public function addRecordFalImage(
        array &$item,
        string $recTable,
        int $recUid,
        int $recPid,
        string $imageField,
        File $fileObject,
        ?array $additionalDataForReference = null,
        array &$dst
    ): void {
        $newId = StringUtility::getUniqueId('NEW');
        $item['destination']['data']['sys_file_reference'][$newId] = array_replace(
            $additionalDataForReference ?: [],
            [
                'table_local' => 'sys_file',
                'uid_local' => $fileObject->getUid(),
                'tablenames' => $recTable,
                'uid_foreign' => $recUid,
                'fieldname' => $imageField,
                'pid' => $recPid,
            ]
        );
        $item['destination']['uc'][$recTable][$recUid]['sys_file_reference'][$newId] = 1;
        $dst['row'] = array_replace(
            $dst['row'],
            [
                $imageField => trim($dst['row'][$imageField] . ',' . $newId, ','),
            ]
        );
        if ($recTable !== 'tt_content' && !isset($dst['row']['pid'])) {
            $dst['row']['pid'] = $recPid;
        }
    }

    public function addFlexFalImage(
        array &$item,
        string $recTable,
        int $recUid,
        int $recPid,
        string $imageField,
        File $fileObject,
        ?array $additionalDataForReference = null,
        array &$dst,
        string $dstPath
    ): void {
        $newId = StringUtility::getUniqueId('NEW');
        $item['destination']['data']['sys_file_reference'][$newId] = array_replace(
            $additionalDataForReference ?: [],
            [
                'table_local' => 'sys_file',
                'uid_local' => $fileObject->getUid(),
                'tablenames' => $recTable,
                'uid_foreign' => $recUid,
                'fieldname' => $imageField,
                'pid' => $recPid,
            ]
        );
        $item['destination']['uc'][$recTable][$recUid]['sys_file_reference'][$newId] = 1;

        $value = (string)(ArrayUtility::isValidPath($dst, $dstPath, '/') ? ArrayUtility::getValueByPath(
            $dst,
            $dstPath,
            '/'
        ) : '');
        $value .= ',' . $newId;
        $value = trim($value, ',');
        $dst = ArrayUtility::setValueByPath($dst, $dstPath, $value, '/');
    }

    /**
     * Give it a non executed QueryBuilder to fetch result as array.
     * You have the possibility to add a col as ArrayKey.
     */
    public function getRowsFromQueryBuilder(QueryBuilder $queryBuilder, string $columnAsKey = ''): array
    {
        $statement = $queryBuilder->execute();
        $rows = [];
        while ($row = $statement->fetchAssociative()) {
            if (!empty($columnAsKey)) {
                $rows[$row[$columnAsKey]] = $row;
            } else {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function clearAllFileReferences(string $table, int $uid): MigrationHelper
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $wheres = [
            $queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter($table)
            ),
            $queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            ),
        ];
        $queryBuilder->delete('sys_file_reference')
            ->where(...$wheres)
            ->execute();
        return $this;
    }

    public function fetchSysFile(?int $fileUID): ?File
    {
        /** @var FileRepository $fileRepository */
        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        try {
            if ($file = $fileRepository->findByUid($fileUID)) {
                if ($file instanceof File) {
                    return $file;
                }
            }
        } catch (Exception $e) {
        }

        return null;
    }
}
