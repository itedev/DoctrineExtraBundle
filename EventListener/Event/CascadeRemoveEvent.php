<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class CascadeRemoveEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class CascadeRemoveEvent extends Event
{
    /**
     * @var EntityManager $em
     */
    private $em;

    /**
     * @var object $rootEntity
     */
    private $rootEntity;

    /**
     * @var string $class
     */
    private $class;

    /**
     * @var array $identifiers
     */
    private $identifiers = [];

    /**
     * @var int $associationType
     */
    private $associationType;

    /**
     * @param EntityManager $em
     * @param object $rootEntity
     * @param string $class
     * @param string $identifiers
     * @param int $associationType
     */
    public function __construct(EntityManager $em, $rootEntity, $class, $identifiers, $associationType)
    {
        $this->em = $em;
        $this->rootEntity = $rootEntity;
        $this->class = $class;
        $this->identifiers = $identifiers;
        $this->associationType = $associationType;
    }

    /**
     * Get entityManager
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Get rootEntity
     *
     * @return object
     */
    public function getRootEntity()
    {
        return $this->rootEntity;
    }

    /**
     * Get class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Get identifiers
     *
     * @return array
     */
    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    /**
     * Get associationType
     *
     * @return int
     */
    public function getAssociationType()
    {
        return $this->associationType;
    }

    /**
     * @return string
     */
    public function getRootClass()
    {
        return get_class($this->rootEntity);
    }

    /**
     * @return bool
     */
    public function isOneToMany()
    {
        return ClassMetadata::ONE_TO_MANY === $this->associationType;
    }

    /**
     * @return bool
     */
    public function isOneToOne()
    {
        return ClassMetadata::ONE_TO_ONE === $this->associationType;
    }
}
