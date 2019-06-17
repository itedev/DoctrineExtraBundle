<?php

namespace ITE\DoctrineExtraBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ODMPass
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ODMPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (
            $container->hasParameter('ite_doctrine_extra.odm.orm_field_subscriber_enabled')
            && true !== $container->getParameter('ite_doctrine_extra.odm.orm_field_subscriber_enabled')
        ) {
            $container->removeDefinition('ite.doctrine_extra.odm.event_subscrber.orm_type');
        } elseif ($container->has('ite.doctrine_extra.odm.event_subscrber.orm_type')) {
            $def = $container->getDefinition('ite.doctrine_extra.odm.event_subscrber.orm_type');
            $def->addTag('doctrine.event_subscriber');
        }
    }
}
