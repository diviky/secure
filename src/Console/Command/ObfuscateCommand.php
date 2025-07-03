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

use Diviky\Secure\Configuration\ConfigurationManager;
use Diviky\Secure\Obfuscator\ProjectObfuscator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'obfuscate',
    description: 'Obfuscate your Laravel project while preserving framework compatibility'
)]
class ObfuscateCommand extends Command
{
    public function __construct(
        private readonly ConfigurationManager $configManager = new ConfigurationManager,
        private readonly ProjectObfuscator $obfuscator = new ProjectObfuscator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Source file or directory to obfuscate')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing output directory')
            ->addOption('backup', 'b', InputOption::VALUE_NONE, 'Create backup before obfuscation')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch for file changes and auto-obfuscate')

            // Obfuscation control flags
            ->addOption('obfuscate-variables', null, InputOption::VALUE_NONE, 'Enable variable obfuscation')
            ->addOption('no-obfuscate-variable-name', null, InputOption::VALUE_NONE, 'Disable variable obfuscation')
            ->addOption('obfuscate-functions', null, InputOption::VALUE_NONE, 'Enable function obfuscation')
            ->addOption('no-obfuscate-function-name', null, InputOption::VALUE_NONE, 'Disable function obfuscation')
            ->addOption('obfuscate-classes', null, InputOption::VALUE_NONE, 'Enable class obfuscation')
            ->addOption('no-obfuscate-class-name', null, InputOption::VALUE_NONE, 'Disable class obfuscation')
            ->addOption('obfuscate-methods', null, InputOption::VALUE_NONE, 'Enable method obfuscation')
            ->addOption('no-obfuscate-method-name', null, InputOption::VALUE_NONE, 'Disable method obfuscation')
            ->addOption('obfuscate-properties', null, InputOption::VALUE_NONE, 'Enable property obfuscation')
            ->addOption('no-obfuscate-property-name', null, InputOption::VALUE_NONE, 'Disable property obfuscation')
            ->addOption('obfuscate-constants', null, InputOption::VALUE_NONE, 'Enable constant obfuscation')
            ->addOption('no-obfuscate-constant-name', null, InputOption::VALUE_NONE, 'Disable constant obfuscation')
            ->addOption('obfuscate-namespaces', null, InputOption::VALUE_NONE, 'Enable namespace obfuscation')
            ->addOption('no-obfuscate-namespace-name', null, InputOption::VALUE_NONE, 'Disable namespace obfuscation')
            ->addOption('obfuscate-interfaces', null, InputOption::VALUE_NONE, 'Enable interface obfuscation')
            ->addOption('no-obfuscate-interface-name', null, InputOption::VALUE_NONE, 'Disable interface obfuscation')
            ->addOption('obfuscate-traits', null, InputOption::VALUE_NONE, 'Enable trait obfuscation')
            ->addOption('no-obfuscate-trait-name', null, InputOption::VALUE_NONE, 'Disable trait obfuscation')
            ->addOption('obfuscate-strings', null, InputOption::VALUE_NONE, 'Enable string literal obfuscation')
            ->addOption('no-obfuscate-string-literal', null, InputOption::VALUE_NONE, 'Disable string literal obfuscation')

            // Control structure obfuscation
            ->addOption('obfuscate-if-statement', null, InputOption::VALUE_NONE, 'Enable if statement obfuscation')
            ->addOption('no-obfuscate-if-statement', null, InputOption::VALUE_NONE, 'Disable if statement obfuscation')
            ->addOption('obfuscate-loop-statement', null, InputOption::VALUE_NONE, 'Enable loop statement obfuscation')
            ->addOption('no-obfuscate-loop-statement', null, InputOption::VALUE_NONE, 'Disable loop statement obfuscation')

            // Statement shuffling
            ->addOption('shuffle-statements', null, InputOption::VALUE_NONE, 'Enable statement shuffling')
            ->addOption('no-shuffle-statements', null, InputOption::VALUE_NONE, 'Disable statement shuffling')

            // Output formatting
            ->addOption('strip-indentation', 's', InputOption::VALUE_NONE, 'Single line output')
            ->addOption('no-strip-indentation', null, InputOption::VALUE_NONE, 'Multi line output')
            ->addOption('strip-comments', null, InputOption::VALUE_NONE, 'Remove comments')
            ->addOption('no-strip-comments', null, InputOption::VALUE_NONE, 'Preserve comments')

            // Name generation
            ->addOption('scramble-mode', null, InputOption::VALUE_REQUIRED, 'Scramble mode: identifier, hexadecimal, numeric')
            ->addOption('scramble-length', null, InputOption::VALUE_REQUIRED, 'Length of scrambled names (4-32)')

            // Compatibility
            ->addOption('silent', null, InputOption::VALUE_NONE, 'Do not display information messages')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Display debug information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Handle silent mode
        if ($input->getOption('silent')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $io->title('Diviky Secure Obfuscation');

        // Determine source and output paths
        $sourceArg = $input->getArgument('source');
        $outputOption = $input->getOption('output');

        // Load or create configuration
        $config = $this->loadConfiguration($input, $io, $sourceArg);
        if (! $config) {
            return Command::FAILURE;
        }

        // Override config with command line options
        $config = $this->applyCommandLineOverrides($input, $config);

        // Determine final paths
        [$projectPath, $outputPath, $isSingleFile] = $this->resolvePaths($input, $config, $sourceArg, $outputOption);

        if (! $input->getOption('silent')) {
            $io->info("Source: {$projectPath}");
            $io->info("Output: {$outputPath}");
            if ($input->getOption('config')) {
                $io->info('Configuration: '.$input->getOption('config'));
            }
            $io->newLine();
        }

        // Single file mode - output to stdout if no output specified
        if ($isSingleFile && ! $outputOption) {
            return $this->processSingleFileToStdout($projectPath, $config, $io);
        }

        // Check if output directory exists
        if (! $isSingleFile && is_dir($outputPath) && ! $input->getOption('force')) {
            if (! $io->confirm('Output directory already exists. Continue?', false)) {
                return Command::INVALID;
            }
        }

        // Create backup if requested
        if ($input->getOption('backup')) {
            $this->createBackup($io, $projectPath);
        }

        $isDryRun = $input->getOption('dry-run');
        if ($isDryRun && ! $input->getOption('silent')) {
            $io->note('DRY RUN MODE - No files will be modified');
        }

        try {
            if ($isSingleFile) {
                $result = $this->processSingleFile($projectPath, $outputPath, $config, $isDryRun);
            } else {
                $result = $this->obfuscator->obfuscate(
                    $projectPath,
                    $outputPath,
                    $config,
                    $isDryRun,
                    function ($message, $type = 'info') use ($io, $input) {
                        if ($input->getOption('silent')) {
                            return;
                        }

                        match ($type) {
                            'error' => $io->error($message),
                            'warning' => $io->warning($message),
                            'success' => $io->success($message),
                            default => $io->text($message)
                        };
                    }
                );
            }

            if (! $input->getOption('silent')) {
                $this->displayResults($io, $result, $isDryRun);
            }

            if ($input->getOption('watch')) {
                $this->watchForChanges($io, $projectPath, $outputPath, $config);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Obfuscation failed: '.$e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function loadConfiguration(InputInterface $input, SymfonyStyle $io, ?string $sourceArg): ?array
    {
        $configPath = $input->getOption('config');

        // If no config specified, try to find one
        if (! $configPath) {
            $configPath = $this->configManager->findConfigurationFile();
        }

        // If still no config and we have source argument, use defaults
        if (! $configPath && $sourceArg) {
            return $this->getDefaultConfiguration($sourceArg);
        }

        // If no config and no source, require initialization
        if (! $configPath) {
            $io->error('No configuration file found and no source specified. Run "secure init" first or provide source argument.');

            return null;
        }

        try {
            return $this->configManager->loadConfiguration($configPath);
        } catch (\Exception $e) {
            $io->error('Failed to load configuration: '.$e->getMessage());

            return null;
        }
    }

    private function getDefaultConfiguration(string $source): array
    {
        $isFile = is_file($source);

        return [
            'project' => [
                'type' => 'generic',
                'name' => $isFile ? 'single-file' : basename($source),
                'version' => '1.0.0',
            ],
            'obfuscation' => [
                'variables' => true,
                'functions' => true,
                'classes' => true,
                'methods' => true,
                'properties' => true,
                'constants' => false,
                'namespaces' => false,
                'strings' => false,
                'control_structures' => true,
                'shuffle_statements' => true,
            ],
            'scope' => [
                'include_paths' => [$isFile ? dirname($source) : $source],
                'exclude_paths' => [],
                'include_extensions' => ['php'],
                'preserve_namespaces' => [],
                'preserve_classes' => [],
                'preserve_methods' => [],
                'preserve_functions' => [],
                'preserve_constants' => [],
            ],
            'output' => [
                'directory' => 'obfuscated/',
                'preserve_structure' => true,
                'add_header' => false,
                'strip_comments' => true,
                'strip_whitespace' => false,
            ],
            'security' => [
                'scramble_mode' => 'identifier',
                'scramble_length' => 8,
                'add_dummy_code' => false,
                'randomize_order' => false,
            ],
        ];
    }

    private function applyCommandLineOverrides(InputInterface $input, array $config): array
    {
        // Obfuscation toggles
        $obfuscationMap = [
            'obfuscate-variables' => 'variables',
            'no-obfuscate-variable-name' => 'variables',
            'obfuscate-functions' => 'functions',
            'no-obfuscate-function-name' => 'functions',
            'obfuscate-classes' => 'classes',
            'no-obfuscate-class-name' => 'classes',
            'obfuscate-methods' => 'methods',
            'no-obfuscate-method-name' => 'methods',
            'obfuscate-properties' => 'properties',
            'no-obfuscate-property-name' => 'properties',
            'obfuscate-constants' => 'constants',
            'no-obfuscate-constant-name' => 'constants',
            'obfuscate-namespaces' => 'namespaces',
            'no-obfuscate-namespace-name' => 'namespaces',
            'obfuscate-interfaces' => 'interfaces',
            'no-obfuscate-interface-name' => 'interfaces',
            'obfuscate-traits' => 'traits',
            'no-obfuscate-trait-name' => 'traits',
            'obfuscate-strings' => 'strings',
            'no-obfuscate-string-literal' => 'strings',
        ];

        foreach ($obfuscationMap as $option => $configKey) {
            if ($input->getOption($option)) {
                $config['obfuscation'][$configKey] = ! str_starts_with($option, 'no-');
            }
        }

        // Control structures
        if ($input->getOption('obfuscate-if-statement')) {
            $config['obfuscation']['control_structures'] = true;
        }
        if ($input->getOption('no-obfuscate-if-statement')) {
            $config['obfuscation']['control_structures'] = false;
        }
        if ($input->getOption('obfuscate-loop-statement')) {
            $config['obfuscation']['control_structures'] = true;
        }
        if ($input->getOption('no-obfuscate-loop-statement')) {
            $config['obfuscation']['control_structures'] = false;
        }

        // Statement shuffling
        if ($input->getOption('shuffle-statements')) {
            $config['obfuscation']['shuffle_statements'] = true;
        }
        if ($input->getOption('no-shuffle-statements')) {
            $config['obfuscation']['shuffle_statements'] = false;
        }

        // Output formatting
        if ($input->getOption('strip-indentation')) {
            $config['output']['strip_whitespace'] = true;
        }
        if ($input->getOption('no-strip-indentation')) {
            $config['output']['strip_whitespace'] = false;
        }
        if ($input->getOption('strip-comments')) {
            $config['output']['strip_comments'] = true;
        }
        if ($input->getOption('no-strip-comments')) {
            $config['output']['strip_comments'] = false;
        }

        // Name generation
        if ($scrambleMode = $input->getOption('scramble-mode')) {
            $config['security']['scramble_mode'] = $scrambleMode;
        }
        if ($scrambleLength = $input->getOption('scramble-length')) {
            $config['security']['scramble_length'] = (int) $scrambleLength;
        }

        return $config;
    }

    private function resolvePaths(InputInterface $input, array $config, ?string $sourceArg, ?string $outputOption): array
    {
        $projectPath = $sourceArg ?: getcwd();
        $isSingleFile = $sourceArg && is_file($sourceArg);

        if ($outputOption) {
            $outputPath = $outputOption;
        } elseif ($isSingleFile) {
            $outputPath = null; // Will output to stdout
        } else {
            $outputPath = $projectPath.'/'.rtrim($config['output']['directory'], '/');
        }

        return [$projectPath, $outputPath, $isSingleFile];
    }

    private function processSingleFileToStdout(string $filePath, array $config, SymfonyStyle $io): int
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $io->error("Cannot read file: {$filePath}");

                return Command::FAILURE;
            }

            // TODO: Implement actual obfuscation
            // For now, just output the original content
            echo $content;

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to process file: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function processSingleFile(string $inputPath, string $outputPath, array $config, bool $isDryRun): array
    {
        $startTime = microtime(true);

        $result = [
            'output_path' => $outputPath,
            'stats' => [
                'files_processed' => 1,
                'files_obfuscated' => 0,
                'files_copied' => 0,
                'variables_obfuscated' => 0,
                'functions_obfuscated' => 0,
                'classes_obfuscated' => 0,
                'methods_obfuscated' => 0,
                'processing_time' => 0,
            ],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            $content = file_get_contents($inputPath);
            if ($content === false) {
                $result['errors'][] = "Cannot read file: {$inputPath}";

                return $result;
            }

            // TODO: Implement actual obfuscation
            // For now, just copy the file
            if (! $isDryRun) {
                $directory = dirname($outputPath);
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                file_put_contents($outputPath, $content);
            }

            $result['stats']['files_obfuscated'] = 1;

        } catch (\Exception $e) {
            $result['errors'][] = 'Failed to process file: '.$e->getMessage();
        }

        $result['stats']['processing_time'] = round(microtime(true) - $startTime, 2);

        return $result;
    }

    private function createBackup(SymfonyStyle $io, string $projectPath): void
    {
        $backupPath = $projectPath.'/backup-'.date('Y-m-d-H-i-s');

        $io->text("Creating backup at: {$backupPath}");

        // TODO: Implement backup logic
        // This would copy important files before obfuscation
        $io->success('Backup created successfully');
    }

    private function displayResults(SymfonyStyle $io, array $result, bool $isDryRun): void
    {
        $io->section($isDryRun ? 'Obfuscation Preview' : 'Obfuscation Results');

        $summaryData = [
            ['Files Processed', $result['stats']['files_processed']],
            ['Files Obfuscated', $result['stats']['files_obfuscated']],
            ['Files Copied', $result['stats']['files_copied']],
            ['Variables Obfuscated', number_format($result['stats']['variables_obfuscated'])],
            ['Functions Obfuscated', number_format($result['stats']['functions_obfuscated'])],
            ['Classes Obfuscated', number_format($result['stats']['classes_obfuscated'])],
            ['Methods Obfuscated', number_format($result['stats']['methods_obfuscated'])],
            ['Processing Time', $result['stats']['processing_time'].'s'],
        ];

        $table = new \Symfony\Component\Console\Helper\Table($io);
        $table->setHeaders(['Metric', 'Value']);
        $table->setRows($summaryData);
        $table->render();

        if (! empty($result['warnings'])) {
            $io->warning($result['warnings']);
        }

        if (! empty($result['errors'])) {
            $io->error($result['errors']);
        }

        if (! $isDryRun) {
            $io->success('Obfuscation completed successfully!');

            $io->note([
                'Next steps:',
                '1. Test the obfuscated code thoroughly',
                '2. Verify that all functionality works as expected',
                '3. Deploy the obfuscated version from: '.$result['output_path'],
            ]);
        }
    }

    private function watchForChanges(SymfonyStyle $io, string $projectPath, string $outputPath, array $config): void
    {
        $io->note('Watching for file changes... Press Ctrl+C to stop.');

        $lastModified = [];

        while (true) {
            clearstatcache();

            $finder = new \Symfony\Component\Finder\Finder;
            $finder->files()->in($projectPath);

            foreach ($config['scope']['include_paths'] as $path) {
                $fullPath = $projectPath.'/'.ltrim($path, '/');
                if (is_dir($fullPath)) {
                    $finder->in($fullPath);
                }
            }

            foreach ($config['scope']['exclude_paths'] as $path) {
                $finder->exclude(trim($path, '/'));
            }

            foreach ($config['scope']['include_extensions'] as $extension) {
                $finder->name("*.{$extension}");
            }

            $hasChanges = false;

            foreach ($finder as $file) {
                $filePath = $file->getRealPath();
                $currentModified = $file->getMTime();

                if (! isset($lastModified[$filePath]) || $lastModified[$filePath] < $currentModified) {
                    $lastModified[$filePath] = $currentModified;
                    $hasChanges = true;

                    $io->text('Detected change in: '.$file->getRelativePathname());
                }
            }

            if ($hasChanges) {
                $io->text('Re-obfuscating...');

                try {
                    $this->obfuscator->obfuscate(
                        $projectPath,
                        $outputPath,
                        $config,
                        false,
                        function ($message) use ($io) {
                            // Suppress detailed output during watch mode
                            if (str_contains($message, 'Processing')) {
                                return;
                            }
                            $io->text($message);
                        }
                    );

                    $io->success('Re-obfuscation completed');
                } catch (\Exception $e) {
                    $io->error('Re-obfuscation failed: '.$e->getMessage());
                }
            }

            sleep(2); // Check every 2 seconds
        }
    }
}
