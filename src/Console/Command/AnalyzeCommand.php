<?php

declare(strict_types=1);

/*
 * This file is part of the Diviky Secure package.
 *
 * (c) Sankar <sankar@diviky.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Diviky\Secure\Console\Command;

use Diviky\Secure\Analyzer\ProjectAnalyzer;
use Diviky\Secure\Configuration\ConfigurationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze your project and preview what will be obfuscated'
)]
class AnalyzeCommand extends Command
{
    public function __construct(
        private readonly ConfigurationManager $configManager = new ConfigurationManager,
        private readonly ProjectAnalyzer $analyzer = new ProjectAnalyzer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed analysis')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Export analysis to file (json|yaml|csv)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Diviky Secure Analysis');

        // Load configuration
        $configPath = $input->getOption('config') ?? $this->configManager->findConfigurationFile();
        if (! $configPath) {
            $io->error('No configuration file found. Run "secure init" first.');

            return Command::FAILURE;
        }

        try {
            $config = $this->configManager->loadConfiguration($configPath);
        } catch (\Exception $e) {
            $io->error('Failed to load configuration: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->info("Using configuration: {$configPath}");
        $io->newLine();

        // Analyze project
        try {
            $analysis = $this->analyzer->analyze(getcwd(), $config);
        } catch (\Exception $e) {
            $io->error('Analysis failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Display summary
        $this->displaySummary($io, $analysis);

        if ($input->getOption('detailed')) {
            $this->displayDetailedAnalysis($io, $analysis);
        }

        // Export if requested
        if ($exportFormat = $input->getOption('export')) {
            $this->exportAnalysis($io, $analysis, $exportFormat);
        }

        return Command::SUCCESS;
    }

    private function displaySummary(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Project Overview');

        $summaryData = [
            ['Project Type', $analysis['project']['type']],
            ['Total Files', $analysis['stats']['total_files']],
            ['PHP Files', $analysis['stats']['php_files']],
            ['Total Lines', number_format($analysis['stats']['total_lines'])],
            ['Classes Found', $analysis['stats']['classes']],
            ['Functions Found', $analysis['stats']['functions']],
            ['Methods Found', $analysis['stats']['methods']],
        ];

        $table = new Table($output = $io);
        $table->setHeaders(['Metric', 'Value']);
        $table->setRows($summaryData);
        $table->render();

        $io->newLine();

        // Obfuscation preview
        $io->section('Obfuscation Preview');

        $obfuscationData = [
            ['Variables', $analysis['obfuscation']['variables']['count'], $analysis['obfuscation']['variables']['enabled'] ? '✅' : '❌'],
            ['Functions', $analysis['obfuscation']['functions']['count'], $analysis['obfuscation']['functions']['enabled'] ? '✅' : '❌'],
            ['Classes', $analysis['obfuscation']['classes']['count'], $analysis['obfuscation']['classes']['enabled'] ? '✅' : '❌'],
            ['Methods', $analysis['obfuscation']['methods']['count'], $analysis['obfuscation']['methods']['enabled'] ? '✅' : '❌'],
            ['Properties', $analysis['obfuscation']['properties']['count'], $analysis['obfuscation']['properties']['enabled'] ? '✅' : '❌'],
            ['Constants', $analysis['obfuscation']['constants']['count'], $analysis['obfuscation']['constants']['enabled'] ? '✅' : '❌'],
        ];

        $table = new Table($output = $io);
        $table->setHeaders(['Element Type', 'Count', 'Will Obfuscate']);
        $table->setRows($obfuscationData);
        $table->render();

        if (! empty($analysis['warnings'])) {
            $io->warning($analysis['warnings']);
        }

        if (! empty($analysis['recommendations'])) {
            $io->note($analysis['recommendations']);
        }
    }

    private function displayDetailedAnalysis(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Detailed Analysis');

        foreach (['classes', 'functions', 'methods', 'properties', 'variables', 'constants'] as $type) {
            if (empty($analysis['details'][$type])) {
                continue;
            }

            $io->text("<info>{$type}:</info>");

            $data = [];
            foreach ($analysis['details'][$type] as $item) {
                $data[] = [
                    $item['name'],
                    $item['file'],
                    $item['line'],
                    $item['will_obfuscate'] ? '✅' : '❌',
                    $item['reason'] ?? '',
                ];
            }

            $table = new Table($output = $io);
            $table->setHeaders(['Name', 'File', 'Line', 'Obfuscate', 'Reason']);
            $table->setRows($data);
            $table->render();

            $io->newLine();
        }
    }

    private function exportAnalysis(SymfonyStyle $io, array $analysis, string $format): void
    {
        $filename = 'secure-analysis.'.$format;

        try {
            switch ($format) {
                case 'json':
                    file_put_contents($filename, json_encode($analysis, JSON_PRETTY_PRINT));
                    break;
                case 'yaml':
                    file_put_contents($filename, \Symfony\Component\Yaml\Yaml::dump($analysis, 4));
                    break;
                case 'csv':
                    $this->exportToCsv($filename, $analysis);
                    break;
                default:
                    $io->error("Unsupported export format: {$format}");

                    return;
            }

            $io->success("Analysis exported to: {$filename}");
        } catch (\Exception $e) {
            $io->error('Failed to export analysis: '.$e->getMessage());
        }
    }

    private function exportToCsv(string $filename, array $analysis): void
    {
        $fp = fopen($filename, 'w');

        // Write headers
        fputcsv($fp, ['Type', 'Name', 'File', 'Line', 'Will Obfuscate', 'Reason']);

        // Write data
        foreach (['classes', 'functions', 'methods', 'properties', 'variables', 'constants'] as $type) {
            if (empty($analysis['details'][$type])) {
                continue;
            }

            foreach ($analysis['details'][$type] as $item) {
                fputcsv($fp, [
                    $type,
                    $item['name'],
                    $item['file'],
                    $item['line'],
                    $item['will_obfuscate'] ? 'Yes' : 'No',
                    $item['reason'] ?? '',
                ]);
            }
        }

        fclose($fp);
    }
}
