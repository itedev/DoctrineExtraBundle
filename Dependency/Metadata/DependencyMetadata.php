<?php

namespace ITE\DoctrineExtraBundle\Dependency\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;
use ITE\DoctrineExtraBundle\Dependency\Counter\EntityCounterInterface;

/**
 * Class DependencyMetadata
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DependencyMetadata
{
    /**
     * @var ClassMetadata $targetClassMetadata
     */
    protected $targetClassMetadata;

    /**
     * @var ClassMetadata $classMetadata
     */
    protected $classMetadata;

    /**
     * @var array $associationNames
     */
    protected $associationNames;

    /**
     * @var EntityCounterInterface $entityCounter
     */
    protected $entityCounter;

    /**
     * @param ClassMetadata $targetClassMetadata
     * @param ClassMetadata $classMetadata
     * @param array $associationNames
     * @param EntityCounterInterface $entityCounter
     */
    public function __construct(
        ClassMetadata $targetClassMetadata,
        ClassMetadata $classMetadata,
        array $associationNames,
        EntityCounterInterface $entityCounter
    ) {
        $this->targetClassMetadata = $targetClassMetadata;
        $this->classMetadata = $classMetadata;
        $this->associationNames = $associationNames;
        $this->entityCounter = $entityCounter;
    }

    /**
     * Get targetClassMetadata
     *
     * @return ClassMetadata
     */
    public function getTargetClassMetadata()
    {
        return $this->targetClassMetadata;
    }

    /**
     * Get classMetadata
     *
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->classMetadata->getName();
    }

    /**
     * Get associationNames
     *
     * @return array
     */
    public function getAssociationNames()
    {
        return $this->associationNames;
    }

    /**
     * @param object $targetEntity
     * @param bool $force
     * @return int
     */
    public function getEntityCount($targetEntity, $force = false)
    {
        return $this->entityCounter->count($targetEntity, $force);
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getAssociationMappings()
    {
        $associationMappings = [];
        foreach ($this->associationNames as $associationName) {
            $associationMapping = $this->classMetadata->getAssociationMapping($associationName);
            $associationMappings[$associationName] = $associationMapping;
        }

        return $associationMappings;
    }

    /**
     * @return bool
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function isDoctrineCascadeRemove()
    {
        foreach ($this->getAssociationMappings() as $associationMapping) {
            if (null === $associationMapping['inversedBy']) {
                // unidirectional
                return false;
            } else {
                // bidirectional
                $targetAssociationMapping = $this->targetClassMetadata->getAssociationMapping(
                    $associationMapping['inversedBy']
                );
                if (false === $targetAssociationMapping['isCascadeRemove']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function isDatabaseCascadeRemove()
    {
        foreach ($this->getAssociationMappings() as $associationMapping) {
            foreach ($associationMapping['joinColumns'] as $joinColumn) {
                if ('cascade' !== strtolower($joinColumn['onDelete'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param object $targetEntity
     * @param bool $force
     * @return bool
     */
    public function hasEntities($targetEntity, $force = false)
    {
        return $this->getEntityCount($targetEntity, $force) > 0;
    }
}
