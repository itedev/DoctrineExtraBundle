<?php

namespace ITE\DoctrineExtraBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class ITEDoctrineExtraExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('domain_event.yml');
        $loader->load('cascade_event.yml');
        $loader->load('dependency.yml');

        $container->setParameter('ite_doctrine_extra.proxy_entity_manager.enabled', $config['proxy_entity_manager']['enabled']);

        if (true === $config['proxy_entity_manager']['enabled']) {
            $loader->load('proxy.yml');
            $container->setParameter('ite_doctrine_extra.proxy_entity_manager.prefix_interceptors', $config['proxy_entity_manager']['prefix_interceptors']);
            $container->setParameter('ite_doctrine_extra.proxy_entity_manager.suffix_interceptors', $config['proxy_entity_manager']['suffix_interceptors']);
        }
    }
}
