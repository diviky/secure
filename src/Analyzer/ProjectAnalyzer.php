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

class AnalysisVisitor extends NodeVisitorAbstract
{
    private string $currentFile;

    private array $analysis;

    private array $config;

    private ?string $currentClass = null;

    private ?string $currentNamespace = null;

    public function __construct(string $filePath, array &$analysis, array $config)
    {
        $this->currentFile = $filePath;
        $this->analysis = &$analysis;
        $this->config = $config;
    }

    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Namespace_:
                $this->currentNamespace = $node->name ? $node->name->toString() : null;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
                $this->handleClass($node);
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->handleFunction($node);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->handleMethod($node);
                break;

            case $node instanceof Node\Stmt\Property:
                $this->handleProperty($node);
                break;

            case $node instanceof Node\Expr\Variable:
                $this->handleVariable($node);
                break;

            case $node instanceof Node\Stmt\Const_:
            case $node instanceof Node\Expr\ClassConstFetch:
                $this->handleConstant($node);
                break;
        }
    }

    private function handleClass(Node $node): void
    {
        $className = $node->name->toString();
        $this->currentClass = $className;
        $this->analysis['stats']['classes']++;

        $willObfuscate = $this->shouldObfuscateClass($className);
        if ($willObfuscate) {
            $this->analysis['obfuscation']['classes']['count']++;
        }

        $this->analysis['details']['classes'][] = [
            'name' => $className,
            'file' => $this->currentFile,
            'line' => $node->getStartLine(),
            'will_obfuscate' => $willObfuscate,
            'reason' => $willObfuscate ? null : $this->getPreservationReason('class', $className),
        ];
    }

    private function handleFunction(Node $node): void
    {
        $functionName = $node->name->toString();
        $this->analysis['stats']['functions']++;

        $willObfuscate = $this->shouldObfuscateFunction($functionName);
        if ($willObfuscate) {
            $this->analysis['obfuscation']['functions']['count']++;
        }

        $this->analysis['details']['functions'][] = [
            'name' => $functionName,
            'file' => $this->currentFile,
            'line' => $node->getStartLine(),
            'will_obfuscate' => $willObfuscate,
            'reason' => $willObfuscate ? null : $this->getPreservationReason('function', $functionName),
        ];
    }

    private function handleMethod(Node $node): void
    {
        $methodName = $node->name->toString();
        $this->analysis['stats']['methods']++;

        $willObfuscate = $this->shouldObfuscateMethod($methodName);
        if ($willObfuscate) {
            $this->analysis['obfuscation']['methods']['count']++;
        }

        $this->analysis['details']['methods'][] = [
            'name' => $methodName,
            'file' => $this->currentFile,
            'line' => $node->getStartLine(),
            'will_obfuscate' => $willObfuscate,
            'reason' => $willObfuscate ? null : $this->getPreservationReason('method', $methodName),
        ];
    }

    private function handleProperty(Node $node): void
    {
        foreach ($node->props as $prop) {
            $propertyName = $prop->name->toString();
            $this->analysis['stats']['properties']++;

            $willObfuscate = $this->shouldObfuscateProperty($propertyName);
            if ($willObfuscate) {
                $this->analysis['obfuscation']['properties']['count']++;
            }

            $this->analysis['details']['properties'][] = [
                'name' => $propertyName,
                'file' => $this->currentFile,
                'line' => $node->getStartLine(),
                'will_obfuscate' => $willObfuscate,
                'reason' => $willObfuscate ? null : $this->getPreservationReason('property', $propertyName),
            ];
        }
    }

    private function handleVariable(Node $node): void
    {
        if (! is_string($node->name)) {
            return; // Skip dynamic variable names
        }

        $variableName = $node->name;
        if (in_array($variableName, ['this', '_GET', '_POST', '_SESSION', '_COOKIE', '_SERVER', '_ENV', '_FILES'])) {
            return; // Skip superglobals
        }

        $this->analysis['stats']['variables']++;

        $willObfuscate = $this->shouldObfuscateVariable($variableName);
        if ($willObfuscate) {
            $this->analysis['obfuscation']['variables']['count']++;
        }

        // Only add to details if it's a significant variable (not too common)
        if (! in_array($variableName, ['i', 'j', 'k', 'x', 'y', 'z', 'temp', 'tmp'])) {
            $this->analysis['details']['variables'][] = [
                'name' => $variableName,
                'file' => $this->currentFile,
                'line' => $node->getStartLine(),
                'will_obfuscate' => $willObfuscate,
                'reason' => $willObfuscate ? null : $this->getPreservationReason('variable', $variableName),
            ];
        }
    }

    private function handleConstant(Node $node): void
    {
        $constantName = null;

        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $constantName = $const->name->toString();
            }
        } elseif ($node instanceof Node\Expr\ClassConstFetch && $node->name instanceof Node\Identifier) {
            $constantName = $node->name->toString();
        }

        if ($constantName) {
            $this->analysis['stats']['constants']++;

            $willObfuscate = $this->shouldObfuscateConstant($constantName);
            if ($willObfuscate) {
                $this->analysis['obfuscation']['constants']['count']++;
            }

            $this->analysis['details']['constants'][] = [
                'name' => $constantName,
                'file' => $this->currentFile,
                'line' => $node->getStartLine(),
                'will_obfuscate' => $willObfuscate,
                'reason' => $willObfuscate ? null : $this->getPreservationReason('constant', $constantName),
            ];
        }
    }

    private function shouldObfuscateClass(string $name): bool
    {
        if (! $this->config['obfuscation']['classes']) {
            return false;
        }

        return ! in_array($name, $this->config['scope']['preserve_classes']);
    }

    private function shouldObfuscateFunction(string $name): bool
    {
        if (! $this->config['obfuscation']['functions']) {
            return false;
        }

        return ! in_array($name, $this->config['scope']['preserve_functions']);
    }

    private function shouldObfuscateMethod(string $name): bool
    {
        if (! $this->config['obfuscation']['methods']) {
            return false;
        }

        return ! in_array($name, $this->config['scope']['preserve_methods']);
    }

    private function shouldObfuscateProperty(string $name): bool
    {
        if (! $this->config['obfuscation']['properties']) {
            return false;
        }

        // Don't obfuscate magic properties
        if (str_starts_with($name, '__')) {
            return false;
        }

        return true;
    }

    private function shouldObfuscateVariable(string $name): bool
    {
        if (! $this->config['obfuscation']['variables']) {
            return false;
        }

        // Don't obfuscate superglobals and special variables
        if (str_starts_with($name, '__') || str_starts_with($name, '_')) {
            return false;
        }

        return true;
    }

    private function shouldObfuscateConstant(string $name): bool
    {
        if (! $this->config['obfuscation']['constants']) {
            return false;
        }

        return ! in_array($name, $this->config['scope']['preserve_constants']);
    }

    private function getPreservationReason(string $type, string $name): string
    {
        switch ($type) {
            case 'class':
                if (in_array($name, $this->config['scope']['preserve_classes'])) {
                    return 'Explicitly preserved in configuration';
                }
                break;
            case 'method':
                if (in_array($name, $this->config['scope']['preserve_methods'])) {
                    return 'Explicitly preserved in configuration';
                }
                if (str_starts_with($name, '__')) {
                    return 'Magic method';
                }
                break;
            case 'property':
                if (str_starts_with($name, '__')) {
                    return 'Magic property';
                }
                break;
            case 'variable':
                if (str_starts_with($name, '__') || str_starts_with($name, '_')) {
                    return 'Special/superglobal variable';
                }
                break;
        }

        return 'Obfuscation disabled in configuration';
    }
}
