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

namespace Diviky\Secure\Obfuscator;

use Diviky\Secure\Obfuscator\NameGenerator\IdentifierNameGenerator;
use Diviky\Secure\Obfuscator\NameGenerator\NameGeneratorInterface;
use Diviky\Secure\Obfuscator\Visitor\ObfuscationVisitor;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ProjectObfuscator
{
    private Parser $parser;

    private PrettyPrinter $printer;

    private Filesystem $filesystem;

    private NameGeneratorInterface $nameGenerator;

    private NameMappingRegistry $nameRegistry;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new PrettyPrinter;
        $this->filesystem = new Filesystem;
        $this->nameGenerator = new IdentifierNameGenerator;
        $this->nameRegistry = new NameMappingRegistry;
        $this->nameRegistry->setNameGenerator($this->nameGenerator);
    }

    public function obfuscate(
        string $projectPath,
        string $outputPath,
        array $config,
        bool $isDryRun = false,
        ?callable $progressCallback = null
    ): array {
        $startTime = microtime(true);

        $result = [
            'output_path' => $outputPath,
            'stats' => [
                'files_processed' => 0,
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

        // Setup name generator
        $this->nameGenerator->configure($config['security']);

        if (! $isDryRun) {
            // Prepare output directory
            if ($this->filesystem->exists($outputPath)) {
                $this->filesystem->remove($outputPath);
            }
            $this->filesystem->mkdir($outputPath);
        }

        $finder = $this->createFinder($projectPath, $config);

        // First pass: collect all symbols
        $this->collectSymbols($finder, $config, $progressCallback);

        // Second pass: obfuscate files
        foreach ($finder as $file) {
            $result['stats']['files_processed']++;

            $relativePath = $file->getRelativePathname();
            $outputFile = $outputPath.'/'.$relativePath;

            if ($progressCallback) {
                $progressCallback("Processing: {$relativePath}");
            }

            try {
                if ($file->getExtension() === 'php') {
                    $obfuscated = $this->obfuscateFile($file->getRealPath(), $config);

                    if ($obfuscated !== null) {
                        if (! $isDryRun) {
                            $this->filesystem->mkdir(dirname($outputFile), 0755);
                            file_put_contents($outputFile, $obfuscated);
                        }
                        $result['stats']['files_obfuscated']++;
                    } else {
                        // Copy non-obfuscated PHP files
                        if (! $isDryRun) {
                            $this->filesystem->copy($file->getRealPath(), $outputFile);
                        }
                        $result['stats']['files_copied']++;
                    }
                } else {
                    // Copy non-PHP files as-is
                    if (! $isDryRun) {
                        $this->filesystem->mkdir(dirname($outputFile), 0755);
                        $this->filesystem->copy($file->getRealPath(), $outputFile);
                    }
                    $result['stats']['files_copied']++;
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to process {$relativePath}: ".$e->getMessage();
            }
        }

        // Copy additional files (composer.json, etc.)
        $this->copyAdditionalFiles($projectPath, $outputPath, $config, $isDryRun);

        // Get statistics from name registry
        $stats = $this->nameRegistry->getStatistics();
        $result['stats']['variables_obfuscated'] = $stats['variables'];
        $result['stats']['functions_obfuscated'] = $stats['functions'];
        $result['stats']['classes_obfuscated'] = $stats['classes'];
        $result['stats']['methods_obfuscated'] = $stats['methods'];

        $result['stats']['processing_time'] = round(microtime(true) - $startTime, 2);

        return $result;
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

    private function collectSymbols(Finder $finder, array $config, ?callable $progressCallback = null): void
    {
        // First pass to collect all symbols for consistent obfuscation
        foreach ($finder as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if ($progressCallback) {
                $progressCallback('Analyzing: '.$file->getRelativePathname());
            }

            try {
                $content = file_get_contents($file->getRealPath());
                if ($content === false) {
                    continue;
                }

                $ast = $this->parser->parse($content);
                if ($ast === null) {
                    continue;
                }

                $collector = new Visitor\SymbolCollectorVisitor($this->nameRegistry, $config);
                $traverser = new NodeTraverser;
                $traverser->addVisitor($collector);
                $traverser->traverse($ast);

            } catch (\Exception $e) {
                // Skip files that can't be parsed
            }
        }
    }

    private function obfuscateFile(string $filePath, array $config): ?string
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }

            $ast = $this->parser->parse($content);
            if ($ast === null) {
                return null;
            }

            $visitor = new ObfuscationVisitor($this->nameRegistry, $config);
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $obfuscatedAst = $traverser->traverse($ast);

            $obfuscatedCode = $this->printer->prettyPrintFile($obfuscatedAst);

            // Add header if configured
            if ($config['output']['add_header']) {
                $header = $this->generateHeader($config);
                $obfuscatedCode = $header."\n".$obfuscatedCode;
            }

            // Strip comments and whitespace if configured
            if ($config['output']['strip_comments'] || $config['output']['strip_whitespace']) {
                $obfuscatedCode = $this->postProcessCode($obfuscatedCode, $config);
            }

            return $obfuscatedCode;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function copyAdditionalFiles(string $projectPath, string $outputPath, array $config, bool $isDryRun): void
    {
        // Copy composer.json but modify it for the obfuscated version
        $composerPath = $projectPath.'/composer.json';
        if (file_exists($composerPath) && ! $isDryRun) {
            $composer = json_decode(file_get_contents($composerPath), true);

            // Add notice about obfuscation
            $composer['description'] = ($composer['description'] ?? '').' (Obfuscated by Diviky Secure)';

            file_put_contents(
                $outputPath.'/composer.json',
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        // Copy other important files
        $filesToCopy = ['.env.example', 'LICENSE', 'README.md', 'artisan'];

        foreach ($filesToCopy as $file) {
            $sourcePath = $projectPath.'/'.$file;
            $targetPath = $outputPath.'/'.$file;

            if (file_exists($sourcePath) && ! $isDryRun) {
                $this->filesystem->copy($sourcePath, $targetPath);
            }
        }
    }

    private function generateHeader(array $config): string
    {
        $projectName = $config['project']['name'] ?? 'Unknown Project';
        $version = $config['project']['version'] ?? '1.0.0';
        $date = date('Y-m-d H:i:s');

        return "<?php\n".
               "/*\n".
               " * {$projectName} v{$version}\n".
               " * Obfuscated by Diviky Secure\n".
               " * Generated on: {$date}\n".
               " * \n".
               " * WARNING: This code has been obfuscated for security.\n".
               " * Unauthorized reverse engineering is prohibited.\n".
               ' */';
    }

    private function postProcessCode(string $code, array $config): string
    {
        if ($config['output']['strip_comments']) {
            // Remove comments while preserving important ones
            $code = preg_replace('/\/\*(?!.*Diviky Secure).*?\*\//s', '', $code);
            $code = preg_replace('/\/\/(?!.*@).*/m', '', $code);
        }

        if ($config['output']['strip_whitespace']) {
            // Minimize whitespace
            $code = preg_replace('/\s+/m', ' ', $code);
            $code = preg_replace('/\s*([{}();,])\s*/m', '$1', $code);
        }

        return trim($code);
    }
}
