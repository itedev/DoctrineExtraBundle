<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;
use ITE\DoctrineExtraBundle\Dependency\Counter\EntityCounter;
use ITE\DoctrineExtraBundle\Dependency\Metadata\ClassMetadata;
use ITE\DoctrineExtraBundle\Dependency\Metadata\DependencyMetadata;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Class DependencyMetadataFactory
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DependencyMetadataFactory implements DependencyMetadataFactoryInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var DependencyMapBuilder
     */
    protected $dependencyMapBuilder;

    /**
     * @param ManagerRegistry $registry
     * @param DependencyMapBuilder $dependencyMapBuilder
     */
    public function __construct(ManagerRegistry $registry, DependencyMapBuilder $dependencyMapBuilder)
    {
        $this->registry = $registry;
        $this->dependencyMapBuilder = $dependencyMapBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($class)
    {
        $entity = null;
        if (is_object($class)) {
            $entity = $class;
            $class = get_class($entity);
        }

        /** @var EntityManager $manager */
        $manager = $this->registry->getManagerForClass($class);
        $doctrineClassMetadata = $manager->getClassMetadata($class);
        $dependencyDoctrineClassMetadatas = $this->dependencyMapBuilder->getDependencyClassMetadatas($class);
        $dependencyMetadatas = [];
        foreach ($dependencyDoctrineClassMetadatas as $dependencyDoctrineClassMetadata) {
            /** @var DoctrineClassMetadata $dependencyDoctrineClassMetadata */
            $dependencyClass = $dependencyDoctrineClassMetadata->getName();
            if (
                $dependencyClass === $class
                || in_array($class, $dependencyDoctrineClassMetadata->parentClasses)
                || in_array($class, $dependencyDoctrineClassMetadata->subClasses)
            ) {

            }

            $associationNames = $this->getAssociationNamesByTargetClass(
                $dependencyDoctrineClassMetadata,
                $doctrineClassMetadata
            );
            $entityCounter = new EntityCounter(
                $manager,
                $doctrineClassMetadata,
                $dependencyDoctrineClassMetadata,
                $associationNames
            );
            $dependencyMetadata = new DependencyMetadata(
                $doctrineClassMetadata,
                $dependencyDoctrineClassMetadata,
                $associationNames,
                $entityCounter
            );
            $dependencyMetadatas[$dependencyClass] = $dependencyMetadata;
        }

        return new ClassMetadata($dependencyMetadatas);
    }

    /**
     * @param DoctrineClassMetadata $classMetadata
     * @param DoctrineClassMetadata $targetClassMetadata
     * @return array
     */
    private function getAssociationNamesByTargetClass(
        DoctrineClassMetadata $classMetadata,
        DoctrineClassMetadata $targetClassMetadata
    ) {
        $associationNames = [];
        foreach ($classMetadata->associationMappings as $associationMapping) {
            if (!$associationMapping['isOwningSide']) {
                continue;
            }

            if (
                $targetClassMetadata->getName() === $associationMapping['targetEntity']
                || in_array($associationMapping['targetEntity'], $targetClassMetadata->parentClasses)
                || in_array($associationMapping['targetEntity'], $targetClassMetadata->subClasses)
            ) {
                $associationNames[] = $associationMapping['fieldName'];
            }
        }

        return $associationNames;
    }
}
