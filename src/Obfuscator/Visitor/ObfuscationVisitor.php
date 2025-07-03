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

class ObfuscationVisitor extends NodeVisitorAbstract
{
    private NameMappingRegistry $nameRegistry;

    private array $config;

    private string $currentClass = '';

    private string $currentNamespace = '';

    public function __construct(NameMappingRegistry $nameRegistry, array $config)
    {
        $this->nameRegistry = $nameRegistry;
        $this->config = $config;
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
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        // Obfuscate class names
        if ($node instanceof Node\Stmt\Class_) {
            $className = $node->name->toString();
            if ($this->nameRegistry->hasMapping('classes', $className, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('classes', $className, $this->currentNamespace);
                $node->name = new Node\Identifier($obfuscatedName);
            }
            $this->currentClass = '';
        }

        // Obfuscate interface names
        if ($node instanceof Node\Stmt\Interface_) {
            $interfaceName = $node->name->toString();
            if ($this->nameRegistry->hasMapping('classes', $interfaceName, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('classes', $interfaceName, $this->currentNamespace);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate trait names
        if ($node instanceof Node\Stmt\Trait_) {
            $traitName = $node->name->toString();
            if ($this->nameRegistry->hasMapping('classes', $traitName, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('classes', $traitName, $this->currentNamespace);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate function names
        if ($node instanceof Node\Stmt\Function_) {
            $functionName = $node->name->toString();
            if ($this->nameRegistry->hasMapping('functions', $functionName, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('functions', $functionName, $this->currentNamespace);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate method names
        if ($node instanceof Node\Stmt\ClassMethod) {
            $methodName = $node->name->toString();
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            if ($this->nameRegistry->hasMapping('methods', $methodName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('methods', $methodName, $context);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate property names
        if ($node instanceof Node\Stmt\Property) {
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            foreach ($node->props as $prop) {
                $propertyName = $prop->name->toString();
                if ($this->nameRegistry->hasMapping('properties', $propertyName, $context)) {
                    $obfuscatedName = $this->nameRegistry->getObfuscatedName('properties', $propertyName, $context);
                    $prop->name = new Node\VarLikeIdentifier($obfuscatedName);
                }
            }
        }

        // Obfuscate property access (e.g., $this->property)
        if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Node\Identifier) {
            $propertyName = $node->name->toString();
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            if ($this->nameRegistry->hasMapping('properties', $propertyName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('properties', $propertyName, $context);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate static property access (e.g., Class::$property)
        if ($node instanceof Node\Expr\StaticPropertyFetch && $node->name instanceof Node\VarLikeIdentifier) {
            $propertyName = $node->name->toString();
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            if ($this->nameRegistry->hasMapping('properties', $propertyName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('properties', $propertyName, $context);
                $node->name = new Node\VarLikeIdentifier($obfuscatedName);
            }
        }

        // Obfuscate class constants
        if ($node instanceof Node\Stmt\ClassConst) {
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            foreach ($node->consts as $const) {
                $constantName = $const->name->toString();
                if ($this->nameRegistry->hasMapping('constants', $constantName, $context)) {
                    $obfuscatedName = $this->nameRegistry->getObfuscatedName('constants', $constantName, $context);
                    $const->name = new Node\Identifier($obfuscatedName);
                }
            }
        }

        // Obfuscate global constants
        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $constantName = $const->name->toString();
                if ($this->nameRegistry->hasMapping('constants', $constantName, $this->currentNamespace)) {
                    $obfuscatedName = $this->nameRegistry->getObfuscatedName('constants', $constantName, $this->currentNamespace);
                    $const->name = new Node\Identifier($obfuscatedName);
                }
            }
        }

        // Obfuscate constant fetch (e.g., Class::CONSTANT)
        if ($node instanceof Node\Expr\ClassConstFetch && $node->name instanceof Node\Identifier) {
            $constantName = $node->name->toString();
            // Try to determine the class context for the constant
            $context = '';
            if ($node->class instanceof Node\Name) {
                $className = $node->class->toString();
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$className : $className;
            } else {
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            }

            if ($this->nameRegistry->hasMapping('constants', $constantName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('constants', $constantName, $context);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate variable names
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            $variableName = $node->name;
            $context = $this->currentClass ?: 'global';
            if ($this->nameRegistry->hasMapping('variables', $variableName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('variables', $variableName, $context);
                $node->name = $obfuscatedName;
            }
        }

        // Obfuscate function calls
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $functionName = $node->name->toString();
            if ($this->nameRegistry->hasMapping('functions', $functionName, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('functions', $functionName, $this->currentNamespace);
                $node->name = new Node\Name($obfuscatedName);
            }
        }

        // Obfuscate method calls
        if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
            $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            if ($this->nameRegistry->hasMapping('methods', $methodName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('methods', $methodName, $context);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate static method calls
        if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
            // Try to determine the class context for the method
            $context = '';
            if ($node->class instanceof Node\Name) {
                $className = $node->class->toString();
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$className : $className;
            } else {
                $context = $this->currentNamespace ? $this->currentNamespace.'\\'.$this->currentClass : $this->currentClass;
            }

            if ($this->nameRegistry->hasMapping('methods', $methodName, $context)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('methods', $methodName, $context);
                $node->name = new Node\Identifier($obfuscatedName);
            }
        }

        // Obfuscate class name in various contexts
        if ($node instanceof Node\Name && ! ($node instanceof Node\Name\FullyQualified)) {
            $name = $node->toString();
            if ($this->nameRegistry->hasMapping('classes', $name, $this->currentNamespace)) {
                $obfuscatedName = $this->nameRegistry->getObfuscatedName('classes', $name, $this->currentNamespace);

                return new Node\Name($obfuscatedName);
            }
        }

        // Obfuscate string literals if enabled
        if ($this->config['obfuscation']['strings'] && $node instanceof Node\Scalar\String_) {
            $node->value = $this->obfuscateString($node->value);
        }

        return null;
    }

    private function obfuscateString(string $value): string
    {
        // Simple string obfuscation - encode as base64 and decode at runtime
        if (strlen($value) > 3 && ! $this->isSystemString($value)) {
            return "base64_decode('".base64_encode($value)."')";
        }

        return $value;
    }

    private function isSystemString(string $value): bool
    {
        // Don't obfuscate system-related strings
        $systemPatterns = [
            '/^[a-zA-Z_][a-zA-Z0-9_]*$/', // Simple identifiers
            '/^\/.*/', // File paths
            '/^https?:\/\//', // URLs
            '/^[A-Z_]+$/', // Constants
            '/^\s*$/', // Whitespace only
        ];

        foreach ($systemPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
