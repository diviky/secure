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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SecureConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('secure');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode('project')
            ->isRequired()
            ->children()
            ->enumNode('type')
            ->values(['laravel', 'package', 'generic'])
            ->defaultValue('generic')
            ->end()
            ->scalarNode('name')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('version')
            ->defaultValue('1.0.0')
            ->end()
            ->end()
            ->end()
            ->arrayNode('obfuscation')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('variables')->defaultTrue()->end()
            ->booleanNode('functions')->defaultTrue()->end()
            ->booleanNode('classes')->defaultTrue()->end()
            ->booleanNode('methods')->defaultTrue()->end()
            ->booleanNode('properties')->defaultTrue()->end()
            ->booleanNode('constants')->defaultTrue()->end()
            ->booleanNode('namespaces')->defaultFalse()->end()
            ->booleanNode('strings')->defaultFalse()->end()
            ->booleanNode('control_structures')->defaultTrue()->end()
            ->booleanNode('shuffle_statements')->defaultFalse()->end()
            ->end()
            ->end()
            ->arrayNode('scope')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('include_paths')
            ->scalarPrototype()->end()
            ->defaultValue(['src/'])
            ->end()
            ->arrayNode('exclude_paths')
            ->scalarPrototype()->end()
            ->defaultValue(['vendor/', 'tests/', 'node_modules/'])
            ->end()
            ->arrayNode('include_extensions')
            ->scalarPrototype()->end()
            ->defaultValue(['php'])
            ->end()
            ->arrayNode('preserve_namespaces')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('preserve_classes')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('preserve_methods')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('preserve_functions')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('preserve_constants')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end()
            ->end()
            ->arrayNode('output')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('directory')
            ->defaultValue('dist/')
            ->end()
            ->booleanNode('preserve_structure')->defaultTrue()->end()
            ->booleanNode('add_header')->defaultTrue()->end()
            ->booleanNode('strip_comments')->defaultTrue()->end()
            ->booleanNode('strip_whitespace')->defaultTrue()->end()
            ->end()
            ->end()
            ->arrayNode('security')
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('scramble_mode')
            ->values(['identifier', 'hexadecimal', 'numeric'])
            ->defaultValue('identifier')
            ->end()
            ->integerNode('scramble_length')
            ->min(4)
            ->max(32)
            ->defaultValue(8)
            ->end()
            ->booleanNode('add_dummy_code')->defaultFalse()->end()
            ->booleanNode('randomize_order')->defaultFalse()->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
