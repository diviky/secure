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

namespace Diviky\Secure\Obfuscator\Visitor;

use Diviky\Secure\Obfuscator\NameMappingRegistry;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class SymbolCollectorVisitor extends NodeVisitorAbstract
{
    private NameMappingRegistry $nameRegistry;

    private array $config;

    private string $currentClass = '';

    private string $currentNamespace = '';

    private array $preservedNames = [];

    public function __construct(NameMappingRegistry $nameRegistry, array $config)
    {
        $this->nameRegistry = $nameRegistry;
        $this->config = $config;

        // Set up preserved names
        $this->preservedNames = [
            'classes' => $config['scope']['preserve_classes'] ?? [],
            'functions' => $config['scope']['preserve_functions'] ?? [],
            'methods' => $config['scope']['preserve_methods'] ?? [],
            'constants' => $config['scope']['preserve_constants'] ?? [],
        ];

        // Add built-in PHP reserved names
        $this->nameRegistry->addReservedNames($this->getPhpReservedNames());
    }

    public function enterNode(Node $node)
    {
        // Track current namespace
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : '';
        }

        // Track current class
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = $node->name->toString();
            $this->collectClass($node);
        }

        // Collect interfaces
        if ($node instanceof Node\Stmt\Interface_) {
            $this->collectInterface($node);
        }

        // Collect traits
        if ($node instanceof Node\Stmt\Trait_) {
            $this->collectTrait($node);
        }

        // Collect functions
        if ($node instanceof Node\Stmt\Function_) {
            $this->collectFunction($node);
        }

        // Collect methods
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->collectMethod($node);
        }

        // Collect properties
        if ($node instanceof Node\Stmt\Property) {
            $this->collectProperties($node);
        }

        // Collect constants
        if ($node instanceof Node\Stmt\ClassConst) {
            $this->collectClassConstants($node);
        }

        // Collect global constants
        if ($node instanceof Node\Stmt\Const_) {
            $this->collectGlobalConstants($node);
        }

        // Collect variables
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            $this->collectVariable($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        // Reset current class when leaving class nodes
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = '';
        }

        return null;
    }

    private function collectClass(Node\Stmt\Class_ $node): void
    {
        $className = $node->name->toString();

        if ($this->shouldObfuscateClass($className)) {
            $this->nameRegistry->getObfuscatedName('classes', $className, $this->currentNamespace);
        }
    }

    private function collectInterface(Node\Stmt\Interface_ $node): void
    {
        $interfaceName = $node->name->toString();

        if ($this->shouldObfuscateInterface($interfaceName)) {
            $this->nameRegistry->getObfuscatedName('classes', $interfaceName, $this->currentNamespace);
        }
    }

    private function collectTrait(Node\Stmt\Trait_ $node): void
    {
        $traitName = $node->name->toString();

        if ($this->shouldObfuscateTrait($traitName)) {
            $this->nameRegistry->getObfuscatedName('classes', $traitName, $this->currentNamespace);
        }
    }

    private function collectFunction(Node\Stmt\Function_ $node): void
    {
        $functionName = $node->name->toString();

        if ($this->shouldObfuscateFunction($functionName)) {
            $this->nameRegistry->getObfuscatedName('functions', $functionName, $this->currentNamespace);
        }
    }

    private function collectMethod(Node\Stmt\ClassMethod $node): void
    {
        $methodName = $node->name->toString();

        if ($this->shouldObfuscateMethod($methodName)) {
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            $this->nameRegistry->getObfuscatedName('methods', $methodName, $context);
        }
    }

    private function collectProperties(Node\Stmt\Property $node): void
    {
        foreach ($node->props as $prop) {
            $propertyName = $prop->name->toString();

            if ($this->shouldObfuscateProperty($propertyName)) {
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
                $this->nameRegistry->getObfuscatedName('properties', $propertyName, $context);
            }
        }
    }

    private function collectClassConstants(Node\Stmt\ClassConst $node): void
    {
        foreach ($node->consts as $const) {
            $constantName = $const->name->toString();

            if ($this->shouldObfuscateConstant($constantName)) {
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
                $this->nameRegistry->getObfuscatedName('constants', $constantName, $context);
            }
        }
    }

    private function collectGlobalConstants(Node\Stmt\Const_ $node): void
    {
        foreach ($node->consts as $const) {
            $constantName = $const->name->toString();

            if ($this->shouldObfuscateConstant($constantName)) {
                $this->nameRegistry->getObfuscatedName('constants', $constantName, $this->currentNamespace);
            }
        }
    }

    private function collectVariable(Node\Expr\Variable $node): void
    {
        $variableName = $node->name;

        if ($this->shouldObfuscateVariable($variableName)) {
            $context = $this->currentClass ?: 'global';
            $this->nameRegistry->getObfuscatedName('variables', $variableName, $context);
        }
    }

    private function shouldObfuscateClass(string $name): bool
    {
        return $this->config['obfuscation']['classes']
            && ! in_array($name, $this->preservedNames['classes'])
            && ! $this->isBuiltInClass($name);
    }

    private function shouldObfuscateInterface(string $name): bool
    {
        return $this->config['obfuscation']['classes']
            && ! in_array($name, $this->preservedNames['classes'])
            && ! $this->isBuiltInClass($name);
    }

    private function shouldObfuscateTrait(string $name): bool
    {
        return $this->config['obfuscation']['classes']
            && ! in_array($name, $this->preservedNames['classes'])
            && ! $this->isBuiltInClass($name);
    }

    private function shouldObfuscateFunction(string $name): bool
    {
        return $this->config['obfuscation']['functions']
            && ! in_array($name, $this->preservedNames['functions'])
            && ! $this->isBuiltInFunction($name);
    }

    private function shouldObfuscateMethod(string $name): bool
    {
        return $this->config['obfuscation']['methods']
            && ! in_array($name, $this->preservedNames['methods'])
            && ! $this->isMagicMethod($name);
    }

    private function shouldObfuscateProperty(string $name): bool
    {
        return $this->config['obfuscation']['properties']
            && ! $this->isMagicProperty($name);
    }

    private function shouldObfuscateConstant(string $name): bool
    {
        return $this->config['obfuscation']['constants']
            && ! in_array($name, $this->preservedNames['constants'])
            && ! $this->isBuiltInConstant($name);
    }

    private function shouldObfuscateVariable(string $name): bool
    {
        return $this->config['obfuscation']['variables']
            && ! $this->isSuperglobal($name)
            && ! $this->isMagicVariable($name);
    }

    private function isBuiltInClass(string $name): bool
    {
        return in_array(strtolower($name), [
            'stdclass', 'exception', 'errorexception', 'throwable', 'error',
            'parseerror', 'typeerror', 'argumentcounterror', 'arithmeticerror',
            'divisionbyzeroerror', 'closure', 'generator', 'datetime', 'datetimeimmutable',
            'dateinterval', 'dateperiod', 'datetimezone', 'splfileinfo', 'splfileobject',
            'reflectionclass', 'reflectionmethod', 'reflectionproperty', 'reflectionfunction',
        ]);
    }

    private function isBuiltInFunction(string $name): bool
    {
        return function_exists($name);
    }

    private function isMagicMethod(string $name): bool
    {
        return in_array($name, [
            '__construct', '__destruct', '__call', '__callStatic', '__get', '__set',
            '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
            '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',
        ]);
    }

    private function isMagicProperty(string $name): bool
    {
        return str_starts_with($name, '__');
    }

    private function isBuiltInConstant(string $name): bool
    {
        return in_array($name, [
            'PHP_VERSION', 'PHP_MAJOR_VERSION', 'PHP_MINOR_VERSION', 'PHP_RELEASE_VERSION',
            'PHP_VERSION_ID', 'PHP_EXTRA_VERSION', 'PHP_ZTS', 'PHP_DEBUG', 'PHP_MAXPATHLEN',
            'PHP_OS', 'PHP_OS_FAMILY', 'PHP_SAPI', 'PHP_EOL', 'PHP_INT_MAX', 'PHP_INT_MIN',
            'PHP_INT_SIZE', 'PHP_FLOAT_MAX', 'PHP_FLOAT_MIN', 'PHP_FLOAT_DIG', 'PHP_FLOAT_EPSILON',
            'true', 'false', 'null', 'TRUE', 'FALSE', 'NULL',
        ]);
    }

    private function isSuperglobal(string $name): bool
    {
        return in_array($name, [
            'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV',
        ]);
    }

    private function isMagicVariable(string $name): bool
    {
        return in_array($name, ['this', 'argc', 'argv']) || str_starts_with($name, '__');
    }

    private function getPhpReservedNames(): array
    {
        return [
            // PHP keywords
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
            'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
            'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'for', 'foreach',
            'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
            'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new',
            'or', 'print', 'private', 'protected', 'public', 'require', 'require_once',
            'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var',
            'while', 'xor', 'yield', 'yield_from',

            // Magic constants
            '__LINE__', '__FILE__', '__DIR__', '__FUNCTION__', '__CLASS__', '__TRAIT__',
            '__METHOD__', '__NAMESPACE__',

            // Built-in types
            'bool', 'int', 'float', 'string', 'array', 'object', 'resource', 'null', 'mixed',
            'void', 'never', 'true', 'false',
        ];
    }
}
