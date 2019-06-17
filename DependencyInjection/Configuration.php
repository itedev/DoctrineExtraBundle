<?php

namespace ITE\DoctrineExtraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ite_doctrine_extra');

        $rootNode
            ->children()
                ->arrayNode('proxy_entity_manager')
                    ->canBeEnabled()
                    ->children()
                        ->variableNode('prefix_interceptors')
                            ->defaultValue([])
                        ->end()
                    ->end()
                    ->children()
                        ->variableNode('suffix_interceptors')
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('odm')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('orm_field_subscriber_enabled')->defaultValue(true)
                    ->end()
                ->end()
        ;

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
