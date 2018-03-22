<?php

namespace ITE\DoctrineExtraBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Class ProxyPass
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ProxyPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->getParameter('ite_doctrine_extra.proxy_entity_manager.enabled')) {
            return;
        }

        $pf = $container->get('ite_doctrine_extra.proxy_factory');
        $emReflection = new \ReflectionClass('Doctrine\ORM\EntityManager');
        $em = $emReflection->newInstanceWithoutConstructor();
        $proxy = $pf->createProxy($em);
        $proxyReflection = new \ReflectionClass(get_class($proxy));

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition instanceof DefinitionDecorator) {
                continue;
            }

            if ('doctrine.orm.entity_manager.abstract' !== $definition->getParent()) {
                continue;
            }

            $public = $definition->isPublic();
            $definition->setPublic(false);

            $container->setDefinition($id.'.delegate', $definition);

            $container->register($id, get_class($proxy))
                ->setFile($proxyReflection->getFileName())
                ->addArgument(new Reference($id.'.delegate'))
                ->addArgument(new Expression("service('ite_doctrine_extra.interceptor_method_factory').createPrefixInterceptorMethods()"))
                ->addArgument(new Expression("service('ite_doctrine_extra.interceptor_method_factory').createSuffixInterceptorMethods()"))
                ->setPublic($public);
        }
    }
}
