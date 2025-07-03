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

namespace Diviky\Secure\Analyzer;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

class ProjectAnalyzer
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function analyze(string $projectPath, array $config): array
    {
        $finder = $this->createFinder($projectPath, $config);

        $analysis = [
            'project' => [
                'type' => $config['project']['type'],
                'path' => $projectPath,
                'config' => $config,
            ],
            'stats' => [
                'total_files' => 0,
                'php_files' => 0,
                'total_lines' => 0,
                'classes' => 0,
                'functions' => 0,
                'methods' => 0,
                'properties' => 0,
                'variables' => 0,
                'constants' => 0,
            ],
            'obfuscation' => [
                'variables' => ['enabled' => $config['obfuscation']['variables'], 'count' => 0],
                'functions' => ['enabled' => $config['obfuscation']['functions'], 'count' => 0],
                'classes' => ['enabled' => $config['obfuscation']['classes'], 'count' => 0],
                'methods' => ['enabled' => $config['obfuscation']['methods'], 'count' => 0],
                'properties' => ['enabled' => $config['obfuscation']['properties'], 'count' => 0],
                'constants' => ['enabled' => $config['obfuscation']['constants'], 'count' => 0],
            ],
            'details' => [
                'classes' => [],
                'functions' => [],
                'methods' => [],
                'properties' => [],
                'variables' => [],
                'constants' => [],
            ],
            'warnings' => [],
            'recommendations' => [],
        ];

        foreach ($finder as $file) {
            $analysis['stats']['total_files']++;

            if ($file->getExtension() === 'php') {
                $analysis['stats']['php_files']++;
                $this->analyzeFile($file->getRealPath(), $analysis, $config);
            }
        }

        $this->generateRecommendations($analysis, $config);

        return $analysis;
    }

    private function createFinder(string $projectPath, array $config): Finder
    {
        $finder = new Finder;
        $finder->files()->followLinks();

        // Include paths
        foreach ($config['scope']['include_paths'] as $path) {
            $fullPath = $projectPath.'/'.ltrim($path, '/');
            if (is_dir($fullPath)) {
                $finder->in($fullPath);
            }
        }

        // Exclude paths
        foreach ($config['scope']['exclude_paths'] as $path) {
            $finder->exclude(trim($path, '/'));
        }

        // Include extensions
        foreach ($config['scope']['include_extensions'] as $extension) {
            $finder->name("*.{$extension}");
        }

        return $finder;
    }

    private function analyzeFile(string $filePath, array &$analysis, array $config): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $analysis['stats']['total_lines'] += substr_count($content, "\n") + 1;

        try {
            $ast = $this->parser->parse($content);
            if ($ast === null) {
                return;
            }

            $visitor = new AnalysisVisitor($filePath, $analysis, $config);
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

        } catch (\Exception $e) {
            $analysis['warnings'][] = "Failed to parse {$filePath}: ".$e->getMessage();
        }
    }

    private function generateRecommendations(array &$analysis, array $config): void
    {
        // Check for Laravel-specific recommendations
        if ($config['project']['type'] === 'laravel') {
            if ($config['obfuscation']['namespaces']) {
                $analysis['warnings'][] = 'Obfuscating namespaces in Laravel projects may break autoloading';
            }

            if ($config['obfuscation']['strings'] && $analysis['stats']['total_files'] > 100) {
                $analysis['recommendations'][] = 'Consider disabling string obfuscation for large Laravel projects to avoid breaking translations and views';
            }
        }

        // Performance recommendations
        if ($analysis['stats']['total_files'] > 500 && $config['obfuscation']['shuffle_statements']) {
            $analysis['recommendations'][] = 'Large project detected. Consider disabling statement shuffling for better performance';
        }

        // Security recommendations
        if (! $config['obfuscation']['variables'] && ! $config['obfuscation']['functions']) {
            $analysis['warnings'][] = 'Very minimal obfuscation enabled. Consider enabling more obfuscation options for better security';
        }
    }
}

