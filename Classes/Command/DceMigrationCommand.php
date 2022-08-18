<?php

declare(strict_types=1);

namespace SB\DceFceMigration\Command;

use SB\DceFceMigration\AbstractMigration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DceMigrationCommand extends Command
{

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $style;

    /**
     * @var array Local storage of $GLOBALS['DCE_FCE_MIGRATIONS']
     */
    protected $migrations = [];

    protected function configure()
    {
        $this->addOption('select', null, InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Select migrations by identifier');
        $this->addOption('run', 'r', InputOption::VALUE_NONE, 'Run migrations', null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrations = $GLOBALS['DCE_FCE_MIGRATIONS'] ?? [];
        if (!is_array($this->migrations)) {
            $this->migrations = [];
        }
        $hasMigrations = $this->migrations !== [];
        $this
            ->init($input, $output)
            ->drawTitle();
        $run = $this->input->getOption('run');
        $runSelection = $this->input->getOption('select');

        if (!$hasMigrations || !$run) {
            $this->listMigrations();
            return 0;
        }
        Bootstrap::initializeBackendAuthentication();

        // @todo Add before all migrations event

        $this->runMigrations($runSelection);

        // @todo Add after all migrations event

        return 0;
    }

    protected function runMigrations(array $runSelection): DceMigrationCommand
    {
        foreach ($this->migrations as $migrationClass) {
            if ($instance = $this->createMigrationInstance($migrationClass)) {
                $description = $instance->getDescription();
                $identifier = $instance->getIdentifier();
                $section = $description . ' [' . $identifier . ']';
                if (!empty($identifier) && $runSelection !== [] && !in_array($identifier, $runSelection)) {
                    $this->style->section($section);
                    $this->style->note('Skipped due not included in selection: ', implode(', ', $runSelection));
                    continue;
                }
                $this->style->section($section);
                $instance->process();
            }
        }

        return $this;
    }

    protected function listMigrations(): DceMigrationCommand
    {
        if ($this->migrations === []) {
            $this->style->section('List of migrations');
            $this->style->note('No migrations registered.');

            $this->style->writeln('');
            $this->style->success('FINISHED');
            return $this;
        }

        $list = [];
        foreach ($this->migrations as $migrationClass) {
            if ($instance = $this->createMigrationInstance($migrationClass)) {
                $list[] = [
                    $migrationClass,
                    $instance->getDescription(),
                    $instance->getIdentifier(),
                ];
            }
        }

        $this->style->section('List of migrations');
        $this->style->table(
            [
                'Class',
                'Description',
                'Identifier',
            ],
            $list
        );

        $this->style->writeln('');
        $this->style->success('FINISHED');
        return $this;
    }

    protected function createMigrationInstance(string $migrationClass): ?AbstractMigration
    {
        try {
            if (class_exists($migrationClass)) {
                $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
                $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
                $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);

                $instance = new $migrationClass(
                    $this->input,
                    $this->output,
                    $this->style,
                    $connectionPool,
                    $flexFormTools,
                    $flexFormService
                );
                if ($instance instanceof AbstractMigration) {
                    return $instance;
                }
            } else {
                $this->style->error('Could not find ' . $migrationClass . ' - try composer install or composer dump-autoload.');
            }
        } catch (Throwable $t) {
            $this->style->error((string)$t);
        }

        return null;
    }

    /**
     * @return $this
     */
    protected function drawTitle(): DceMigrationCommand
    {
        $this->style->title($this->getDescription());
        $this->style->writeln('');

        return $this;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this
     */
    protected function init(InputInterface $input, OutputInterface $output): DceMigrationCommand
    {
        $this->style = new SymfonyStyle($input, $output);
        $this->input = $input;
        $this->output = $output;

        return $this;
    }
}
