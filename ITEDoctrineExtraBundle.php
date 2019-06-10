<?php

namespace ITE\DoctrineExtraBundle;

use Doctrine\ODM\MongoDB\Types\Type;
use ITE\Common\Util\ReflectionUtils;
use ITE\DoctrineExtraBundle\DependencyInjection\Compiler\ProxyPass;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\ContainerAwareRepositoryFactory;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Hydrator\HydratorFactory;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Persisters\PersistenceBuilder;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Types\ORMType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class ITEDoctrineExtraBundle
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class ITEDoctrineExtraBundle extends Bundle
{
    public function __construct()
    {
        if (class_exists(Type::class)) {
            Type::registerType('orm', ORMType::class);
        }
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        if ($this->container->has('ite_doctrine_extra.proxy_factory')) {
            $proxyFactory = $this->container->get('ite_doctrine_extra.proxy_factory');
            spl_autoload_register($proxyFactory->getProxyAutoloader());
        }

        if ($this->container->getParameter('ite_doctrine_extra.odm.enabled')) {
            Type::registerType('orm', ORMType::class);
            Type::getType('orm')->setEm($this->container->get('doctrine.orm.default_entity_manager'));

            $config  = $this->container->get('doctrine_mongodb.odm.default_configuration');
            $factory = new ContainerAwareRepositoryFactory($this->container);
            $config->setRepositoryFactory($factory);
            $dm  = $this->container->get('doctrine_mongodb.odm.document_manager');
            $uow = $dm->getUnitOfWork();
            ReflectionUtils::setValue($dm, 'repositoryFactory', $factory);
            $hydratorFactory = new HydratorFactory(
                $dm,
                $dm->getEventManager(),
                $dm->getConfiguration()->getHydratorDir(),
                $dm->getConfiguration()->getHydratorNamespace(),
                $dm->getConfiguration()->getAutoGenerateHydratorClasses()
            );
            ReflectionUtils::setValue($dm, 'hydratorFactory', $hydratorFactory);
            $persistenceBuilder = new PersistenceBuilder($dm, $uow);
            ReflectionUtils::setValue($uow, 'persistenceBuilder', $persistenceBuilder);
        }

        parent::boot();
    }

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ProxyPass());
    }
}
