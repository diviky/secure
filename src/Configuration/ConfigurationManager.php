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

namespace Diviky\Secure\Configuration;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class ConfigurationManager
{
    private readonly Processor $processor;

    private readonly ConfigurationInterface $definition;

    public function __construct()
    {
        $this->processor = new Processor;
        $this->definition = new SecureConfiguration;
    }

    public function loadConfiguration(string $path): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Configuration file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Unable to read configuration file: {$path}");
        }

        $config = Yaml::parse($content);
        if (! is_array($config)) {
            throw new \InvalidArgumentException("Invalid configuration format in: {$path}");
        }

        return $this->processor->processConfiguration($this->definition, [$config]);
    }

    public function writeConfiguration(string $path, array $config): bool
    {
        try {
            $yaml = Yaml::dump($config, 4, 2);

            return file_put_contents($path, $yaml) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function validateConfiguration(array $config): array
    {
        return $this->processor->processConfiguration($this->definition, [$config]);
    }

    public function findConfigurationFile(?string $startPath = null): ?string
    {
        $startPath = $startPath ?? getcwd();
        $searchPaths = [
            $startPath.'/secure.yaml',
            $startPath.'/secure.yml',
            $startPath.'/.secure.yaml',
            $startPath.'/.secure.yml',
            $startPath.'/config/secure.yaml',
            $startPath.'/config/secure.yml',
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
