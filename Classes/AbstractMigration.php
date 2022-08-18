<?php

declare(strict_types=1);

namespace SB\DceFceMigration;

use function json_encode;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractMigration
 */
abstract class AbstractMigration
{
    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var SymfonyStyle */
    protected $style;

    /** @var string */
    protected $_description = 'Element description';

    /** @var ConnectionPool */
    protected $connectionPool;

    /** @var MigrationHelper */
    protected $migrationHelper;

    /** @var FlexFormTools */
    protected $flexFormTools;

    /** @var FlexFormService */
    protected $flexFormService;

    /**
     * @var array
     */
    protected $config = [

        'source' => [
            'table' => 'tt_content',
            'fetch_identifier' => [
                // 'CType' => '',
            ],
            'flexFormField' => 'pi_flexform',

            'fetch_fal_images' => [
//                [
//                    'src_field'     => 'images',        // element image field (row-field or flexform virtual field name)
//                    'src_table'     => 'tt_content',    // table of element (in general tt_content)
//                    'dst_name'      => 'images',        // internal fal collection name
//
//                    // new (optional) - additional where conditions for sys_file_reference table
//                    'wheres'        => [
//                        'deleted'   => 0,
//                        'hidden'    => 0,
//                    ],
//                ],
            ],
        ],

        'destination' => [
            'flexFormField' => 'pi_flexform',
            'clearFlexFormField' => false,
            'clearAllFileReferences' => false,

            // 1. Destination type settings for proper datahandling on data update
            'change' => [
                // 'CType'     => '',
            ],

            // 2. Destination default Data
            'default' => [],
        ],

        'migration' => [
            'mapping' => [], // manual set or set through createMappingDefinition()
        ],
    ];

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
        $this->migrationHelper = GeneralUtility::makeInstance(
            MigrationHelper::class,
            $this->input,
            $this->output,
            $this->style,
            $this->connectionPool,
            $this->flexFormTools,
            $this->flexFormService
        );
        $this->config['migration']['mapping'] = $this->config['migration']['mapping'] ?? [];
        $this->createMappingDefinition($this->config['migration']['mapping']);

        $this->addDynamicDefault($this->config['destination']['default']);

        $this->config['destination']['default'][$this->destinationFlexFormField()] = $this->config['destination']['default'][$this->destinationFlexFormField()] ?? [];
        $this->addDynamicDefaultFlex($this->config['destination']['default'][$this->destinationFlexFormField()]);
    }

    abstract protected function manualMigration(
        array &$src,
        array &$dst,
        array &$item
    ): void;

    abstract protected function beforeUpdate(
        array &$item
    ): void;

    abstract protected function afterUpdate(
        array &$item
    ): void;

    abstract protected function createMappingDefinition(array &$return): void;

    final protected function source(): Connection
    {
        return $this->connectionPool->getConnectionForTable($this->sourceTable());
    }

    final public function process()
    {
        if (!($rows = $this->fetchSource())) {
            $this->style->note('Nothing to do');
            $this->style->writeln('');
            return $this;
        }

        foreach ($rows as $row) {
            $this->migrateRow($row);
        }

        $this->style->success('Finished');
        $this->style->writeln('');

        return $this;
    }

    final protected function migrateRow(array $item): void
    {
        $this
            ->fetchFalImages($item)
            ->processMapping($item)
            ->processMethod($item)
            ->processBeforeUpdate($item)
            ->processUpdate($item)
            ->processAfterUpdate($item)

            //->collectCacheWarmup($item)
            ->displayRowState($item);
    }

    final protected function fetchFalImages(array &$item)
    {
        if ($this->sourceFetchFalImages()) {
            foreach ($this->sourceFetchFalImages() as $options) {
                $srcTable = $options['src_table'] ?? 'tt_content';
                $srcField = $options['src_field'] ?? null;
                $dstName = $options['dst_name'] ?? 'fal';
                $aWheres = $options['wheres'] ?? null;
                if (!array_key_exists($dstName, $item['src_fal'])) {
                    $item['src_fal'][$dstName] = [];
                }

                /** @var FileReference[] $images */
                $images = $this->migrationHelper()->getFalImages(
                    $srcTable,
                    $srcField,
                    (int)$item['source']['uid'],
                    $aWheres
                );
                if ($images) {
                    foreach ($images as $uid => $image) {
                        $item['src_fal'][$dstName][] = $image;
                    }
                }
            }
        }

        return $this;
    }

    final protected function processMapping(array &$item)
    {
        if ($this->destinationMapping()) {
            $src = [
                'row' => $item['source'],
                'flex' => $item['flex'],
                'fal' => $item['src_fal'],

                'tablename' => $this->sourceTable(),
                'pid' => (int)$item['source']['pid'],
            ];
            $dst = [
                'row' => $item['destination']['data'][$this->sourceTable()][(int)$item['source']['uid']],
                'flex' => $item['destination']['flex'],
            ];
            $this->migrationHelper()->doMapping(
                $this->destinationMapping(),
                $src,
                $dst,
                $item
            );

            $item['destination']['data'][$this->sourceTable()][(int)$item['source']['uid']] = $dst['row'];
            $item['destination']['flex'] = $dst['flex'];
        }

        return $this;
    }

    final protected function processMethod(array &$item)
    {
        $_s = [
            'row' => $item['source'],
            'flex' => $item['flex'],
        ];
        $_d = [
            'row' => $item['destination']['data'][$this->sourceTable()][(int)$item['source']['uid']],
            'flex' => $item['destination']['flex'],
        ];

        $this->manualMigration(
            $_s,
            $_d,
            $item
        );

        $item['destination']['data'][$this->sourceTable()][(int)$item['source']['uid']] = $_d['row'];
        $item['destination']['flex'] = $_d['flex'];

        return $this;
    }

    final protected function processUpdate(array &$item)
    {
        $cmd = [];
        $change = $item['destination']['change'];
        $data = $item['destination']['data'];
        $clearFlexForm = $item['destination']['clearFlexFormField'] ?? false;
        $uc = [];

        $data[$this->sourceTable()][(int)$item['source']['uid']][$this->destinationFlexFormField()]
            = array_replace_recursive(
                $data[$this->sourceTable()][(int)$item['source']['uid']][$this->destinationFlexFormField()] ?? [],
                $item['destination']['flex']
            );

        if (empty($data['sys_file_reference'])) {
            unset($data['sys_file_reference']);
        }

        if (!empty($data['sys_file_reference'])) {
            $uc['inlineView'][$this->sourceTable()][(int)$item['source']['uid']]['sys_file_reference'] = [];
            foreach ($data['sys_file_reference'] as $refId => $refData) {
                $uc['inlineView'][$this->sourceTable()][(int)$item['source']['uid']]['sys_file_reference'][$refId] = 1;
            }
        }

        if ($clearFlexForm === true) {
            $data[$this->sourceTable()][(int)$item['source']['uid']][$this->destinationFlexFormField()] = null;
        }

        /** @var DataHandler $dh */
        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $dh->enableLogging = true;
        $dh->start($change, $cmd);
        $res = $dh->process_datamap();
        $dh->processRemapStack();

        if ($dh->errorLog) {
            $this->style->error('Updating record failed');
            $this->style->writeln('');
            $this->style->writeln(json_encode($dh->errorLog, JSON_PRETTY_PRINT));
            $this->style->writeln('');

            $item['updated'] = false;
            $item['errorLog'] = $dh->errorLog;
            return $this;
        }

        if (true === ($item['destination']['clearAllFileReferences'] ?? false)) {
            $this->migrationHelper()->clearAllFileReferences($this->sourceTable(), (int)$item['source']['uid']);
        }

        /** @var DataHandler $dh */
        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $dh->enableLogging = true;
        $dh->start($data, $cmd);
        $dh->process_datamap();
        $dh->processRemapStack();

        if (!empty($uc)) {
            FormEngineUtility::updateInlineView($uc, $dh);
        }

        if ($dh->errorLog) {
            $this->style->error('Updating record failed');
            $this->style->writeln('');
            $this->style->writeln(json_encode($dh->errorLog, JSON_PRETTY_PRINT));
            $this->style->writeln('');

            $item['updated'] = false;
            $item['errorLog'] = $dh->errorLog;
            return $this;
        }

        $item['updated'] = true;
        return $this;
    }

    final protected function processAfterUpdate(array &$item)
    {
        $this->afterUpdate($item);
        return $this;
    }

    final protected function processBeforeUpdate(array &$item)
    {
        $this->beforeUpdate($item);
        return $this;
    }

    /**
     * @return array|null
     */
    protected function fetchSource(): ?array
    {
        $return = null;
        try {
            if (empty($this->sourceFetchIdentifier())) {
                $this->style->error('Source fetch identifier empty - to open; please nail it further');
                $this->style->writeln('');
                return null;
            }
            $this->style->write('Fetch source records for ' . $this->_description . ' ... ');

            $qb = $this->source()->createQueryBuilder();
            $qb
                ->select('*')
                ->from($this->sourceTable())
                ->getRestrictions()->removeAll();
            $sourceIdentifiers = $this->sourceFetchIdentifier();
            foreach ($sourceIdentifiers as $identifier => $value) {
                $qb->andWhere(
                    $qb->expr()->eq(
                        $identifier,
                        $qb->createNamedParameter($value)
                    )
                );
            }

            $result = $qb->execute()->fetchAllAssociative();
            $this->style->writeln('DONE - ' . count($result) . ' source elements');

            if (!empty($result)) {
                $this->style->write('Prepare source elements ... ');
                foreach ($result as $r) {
                    $return[] = [
                        'srcUid' => (int)$r['uid'],
                        'srcPid' => (int)$r['pid'],
                        'srcTable' => $this->sourceTable(),
                        'src_fal' => [],
                        'source' => $r,
                        'flex' => $this->prepareFlex(
                            $r,
                            $this->sourceFlexFormField(),
                            $this->sourceTable()
                        ),

                        'destination' => [
                            'uid' => (int)$r['uid'],
                            'table' => $this->sourceTable(),
                            'clearFlexFormField' => $this->config['destination']['clearFlexFormField'] ?? false,
                            'clearAllFileReferences' => $this->config['destination']['clearAllFileReferences'] ?? false,

                            'uc' => [],

                            'change' => [
                                $this->sourceTable() => [
                                    (int)$r['uid'] => $this->destinationDataHandlerChange(),
                                ],
                            ],

                            'data' => [

                                $this->sourceTable() => [
                                    (int)$r['uid'] => $this->destinationDefaultData(),
                                ],

                                'sys_file_reference' => [],
                            ],

                            'flex' => $this->destinationDefaultData()[$this->destinationFlexFormField()] ?? [],

                        ],

                        'updated' => null,
                        'errorLog' => [],

                        'storage' => [],
                    ];
                }

                $this->style->writeln('DONE');
            }
        } catch (Throwable $t) {
            $return = null;
            $this->style->writeln('FAILED');
            $this->style->error('FetchSource failed: ' . $t->getMessage());
            $this->style->writeln('');
        }

        return $return;
    }

    /**
     * @param array $row
     * @param string $flexField
     * @param string $table
     * @return array|null
     */
    protected function prepareFlex(array $row, string $flexField, string $table): ?array
    {
        if (empty($flexField)) {
            return null;
        }
        if (!array_key_exists($flexField, $row) || empty($row[$flexField])) {
            return null;
        }

        return GeneralUtility::xml2array($row[$flexField]);
    }

    public function getDescription(): string
    {
        return $this->_description;
    }

    public function getIdentifier(): string
    {
        $replaces = [
            ' ' => '-',
        ];
        return trim(str_replace(
            array_keys($replaces),
            array_values($replaces),
            mb_strtolower($this->getDescription())
        ), '-_ ');
    }

    protected function addDynamicDefault(array &$default): void
    {
    }

    protected function addDynamicDefaultFlex(array &$default): void
    {
    }

    protected function migrationHelper(): MigrationHelper
    {
        return $this->migrationHelper;
    }

    protected function sourceTable(): string
    {
        return $this->config['source']['table'] ?? 'tt_content';
    }

    protected function sourceFetchIdentifier(): array
    {
        return $this->config['source']['fetch_identifier'] ?? [];
    }

    protected function sourceFlexFormField(): string
    {
        return $this->config['source']['flexFormField'] ?? 'pi_flexform';
    }

    protected function sourceFetchFalImages(): array
    {
        return $this->config['source']['fetch_fal_images'] ?? [];
    }

    protected function destinationDataHandlerChange(): array
    {
        return $this->config['destination']['change'] ?? [];
    }

    protected function destinationDefaultData(): array
    {
        return $this->config['destination']['default'];
    }

    protected function destinationFlexFormField(): string
    {
        return $this->config['destination']['flexFormField'] ?? 'pi_flexform';
    }

    protected function destinationMapping(): array
    {
        return $this->config['migration']['mapping'] ?? [];
    }

    protected function displayRowState(array &$item)
    {
        $type = 'I';
        $contentUID = (int)$item['srcUid'];
        $srcCType = $item['source']['CType'];
        $dstCType = $item['destination']['change'][$this->sourceTable()][(int)$item['srcUid']]['CType'] ?? $item['source']['CType'];
        $message = $item['updated'] ? 'updated' : 'not-updated';

        if (!empty($item['errorLog'])) {
            $type = 'E';
            $message = 'failed : ' . json_encode($item['errorLog']);
        }

        $this->style->writeln(
            sprintf(
                '[%s][ContentUID: %7s][%s => %s] %s',
                $type,
                $contentUID,
                $srcCType,
                $dstCType,
                $message
            )
        );
        return $this;
    }
}
