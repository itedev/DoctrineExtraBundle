<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event;

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
     * @var object $rootEntity
     */
    private $rootEntity;

    /**
     * @var string $dependencyClass
     */
    private $dependencyClass;

    /**
     * @var array $dependencyIdentifiers
     */
    private $dependencyIdentifiers = [];

    /**
     * @var int $associationType
     */
    private $associationType;

    /**
     * @param object $rootEntity
     * @param string $dependencyClass
     * @param string $dependencyIdentifiers
     * @param int $associationType
     */
    public function __construct($rootEntity, $dependencyClass, $dependencyIdentifiers, $associationType)
    {
        $this->rootEntity = $rootEntity;
        $this->dependencyClass = $dependencyClass;
        $this->dependencyIdentifiers = $dependencyIdentifiers;
        $this->associationType = $associationType;
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
