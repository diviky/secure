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

use Diviky\Secure\Obfuscator\NameGenerator\NameGeneratorInterface;

class NameMappingRegistry
{
    private array $mappings = [
        'variables' => [],
        'functions' => [],
        'classes' => [],
        'methods' => [],
        'properties' => [],
        'constants' => [],
        'namespaces' => [],
    ];

    private array $reservedNames = [];

    private NameGeneratorInterface $nameGenerator;

    public function setNameGenerator(NameGeneratorInterface $nameGenerator): void
    {
        $this->nameGenerator = $nameGenerator;
    }

    public function addReservedName(string $name): void
    {
        $this->reservedNames[] = $name;
    }

    public function addReservedNames(array $names): void
    {
        $this->reservedNames = array_merge($this->reservedNames, $names);
    }

    public function getObfuscatedName(string $type, string $originalName, string $context = ''): string
    {
        $key = $this->createKey($type, $originalName, $context);

        if (isset($this->mappings[$type][$key])) {
            return $this->mappings[$type][$key];
        }

        // Generate new obfuscated name
        do {
            $obfuscatedName = $this->nameGenerator->generate();
        } while (in_array($obfuscatedName, $this->reservedNames) || $this->isNameUsed($obfuscatedName));

        $this->mappings[$type][$key] = $obfuscatedName;

        return $obfuscatedName;
    }

    public function hasMapping(string $type, string $originalName, string $context = ''): bool
    {
        $key = $this->createKey($type, $originalName, $context);

        return isset($this->mappings[$type][$key]);
    }

    public function getOriginalName(string $type, string $obfuscatedName): ?string
    {
        foreach ($this->mappings[$type] as $key => $obfuscated) {
            if ($obfuscated === $obfuscatedName) {
                return $this->extractOriginalFromKey($key);
            }
        }

        return null;
    }

    public function getAllMappings(): array
    {
        return $this->mappings;
    }

    public function getStatistics(): array
    {
        return [
            'variables' => count($this->mappings['variables']),
            'functions' => count($this->mappings['functions']),
            'classes' => count($this->mappings['classes']),
            'methods' => count($this->mappings['methods']),
            'properties' => count($this->mappings['properties']),
            'constants' => count($this->mappings['constants']),
            'namespaces' => count($this->mappings['namespaces']),
            'total' => array_sum(array_map('count', $this->mappings)),
        ];
    }

    public function exportMappings(): string
    {
        return json_encode($this->mappings, JSON_PRETTY_PRINT);
    }

    public function importMappings(string $json): void
    {
        $imported = json_decode($json, true);
        if (is_array($imported)) {
            $this->mappings = array_merge($this->mappings, $imported);
        }
    }

    private function createKey(string $type, string $originalName, string $context): string
    {
        if ($context) {
            return "{$originalName}::{$context}";
        }

        return $originalName;
    }

    private function extractOriginalFromKey(string $key): string
    {
        return explode('::', $key)[0];
    }

    private function isNameUsed(string $name): bool
    {
        foreach ($this->mappings as $type => $mappings) {
            if (in_array($name, $mappings)) {
                return true;
            }
        }

        return false;
    }
}
