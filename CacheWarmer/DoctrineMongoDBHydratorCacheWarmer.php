<?php

namespace ITE\DoctrineExtraBundle\CacheWarmer;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Types\Type;
use ITE\Common\Util\ReflectionUtils;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Hydrator\HydratorFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class DoctrineMongoDBHydratorCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function isOptional()
    {
        return false;
    }

    public function warmUp($cacheDir)
    {
        /* @var $registry \Doctrine\Common\Persistence\ManagerRegistry */
        $registry = $this->container->get('doctrine_mongodb');
        /** @var DocumentManager $dm */
        foreach ($registry->getManagers() as $dm) {
            $hydratorFactory = new HydratorFactory($dm, $dm->getEventManager(), $dm->getConfiguration()->getHydratorDir(), $dm->getConfiguration()->getHydratorNamespace(), $dm->getConfiguration()->getAutoGenerateHydratorClasses());
            ReflectionUtils::setValue($dm, 'hydratorFactory', $hydratorFactory);
            /* @var $dm \Doctrine\ODM\MongoDB\DocumentManager */
            $classes = $dm->getMetadataFactory()->getAllMetadata();
            $dm->getHydratorFactory()->generateHydratorClasses($classes);
        }
    }
}
