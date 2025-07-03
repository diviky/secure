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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize Secure configuration for your Laravel project'
)]
class InitCommand extends Command
{
    public function __construct(
        private readonly ConfigurationManager $configManager = new ConfigurationManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
            ->addOption('laravel', null, InputOption::VALUE_NONE, 'Initialize for Laravel project')
            ->addOption('package', null, InputOption::VALUE_NONE, 'Initialize for package development');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Diviky Secure Initialization');

        $configPath = getcwd().'/secure.yaml';

        if (file_exists($configPath) && ! $input->getOption('force')) {
            $io->warning('Configuration file already exists. Use --force to overwrite.');

            return Command::FAILURE;
        }

        $isLaravel = $input->getOption('laravel') || $this->detectLaravel();
        $isPackage = $input->getOption('package');

        $config = $this->generateConfiguration($isLaravel, $isPackage);

        if ($this->configManager->writeConfiguration($configPath, $config)) {
            $io->success('Configuration created successfully at: '.$configPath);

            if ($isLaravel) {
                $io->info('Laravel project detected. Configuration optimized for Laravel applications.');
            }

            $io->note([
                'Next steps:',
                '1. Review and customize the configuration in secure.yaml',
                '2. Run "secure analyze" to see what will be obfuscated',
                '3. Run "secure obfuscate" to obfuscate your code',
            ]);

            return Command::SUCCESS;
        }

        $io->error('Failed to create configuration file.');

        return Command::FAILURE;
    }

    private function detectLaravel(): bool
    {
        return file_exists(getcwd().'/artisan') &&
               file_exists(getcwd().'/composer.json') &&
               str_contains(file_get_contents(getcwd().'/composer.json'), 'laravel/framework');
    }

    private function generateConfiguration(bool $isLaravel, bool $isPackage): array
    {
        $config = [
            'project' => [
                'type' => $isLaravel ? 'laravel' : ($isPackage ? 'package' : 'generic'),
                'name' => basename(getcwd()),
                'version' => '1.0.0',
            ],
            'obfuscation' => [
                'variables' => true,
                'functions' => true,
                'classes' => true,
                'methods' => true,
                'properties' => true,
                'constants' => true,
                'namespaces' => false, // Keep namespaces readable for Laravel
                'strings' => false, // Careful with strings in Laravel
                'control_structures' => true,
                'shuffle_statements' => false, // Disabled by default for better performance
            ],
            'scope' => [
                'include_paths' => $isLaravel ? ['app/', 'packages/'] : ['src/'],
                'exclude_paths' => ['vendor/', 'node_modules/', 'tests/', 'storage/', 'bootstrap/cache/'],
                'include_extensions' => ['php'],
                'preserve_namespaces' => $isLaravel ? ['Illuminate\\', 'Laravel\\'] : [],
                'preserve_classes' => $isLaravel ? ['Model', 'Controller', 'Middleware', 'ServiceProvider'] : [],
                'preserve_methods' => $isLaravel ? ['boot', 'register', 'handle', 'render'] : [],
            ],
            'output' => [
                'directory' => 'dist/',
                'preserve_structure' => true,
                'add_header' => true,
                'strip_comments' => true,
                'strip_whitespace' => true,
            ],
            'security' => [
                'scramble_mode' => 'identifier',
                'scramble_length' => 8,
                'add_dummy_code' => false,
                'randomize_order' => false,
            ],
        ];

        return $config;
    }
}
