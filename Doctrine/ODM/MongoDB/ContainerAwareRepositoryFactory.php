<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Repository\AbstractRepositoryFactory;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContainerAwareRepositoryFactory
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ContainerAwareRepositoryFactory extends AbstractRepositoryFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * ContainerAwareRepositoryFactory constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    protected function instantiateRepository(
        $repositoryClassName,
        DocumentManager $documentManager,
        ClassMetadata $metadata
    ) {
        $repo = new $repositoryClassName($documentManager, $documentManager->getUnitOfWork(), $metadata);

        if ($repo instanceof ContainerAwareInterface) {
            $repo->setContainer($this->container);
        }

        return $repo;
    }
}
