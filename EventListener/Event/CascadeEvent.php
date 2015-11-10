<?php


namespace ITE\DoctrineExtraBundle\EventListener\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class CascadeEvent
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeEvent extends Event
{
    /**
     * @var string
     */
    private $eventName;

    /**
     * @var object
     */
    private $entity;

    /**
     * @var object
     */
    private $parentEntity;

    /**
     * CascadeEvent constructor.
     *
     * @param string $eventName
     * @param object $entity
     * @param object $parentEntity
     */
    public function __construct($eventName, $entity, $parentEntity)
    {
        $this->eventName    = $eventName;
        $this->entity       = $entity;
        $this->parentEntity = $parentEntity;
    }

    /**
     * Get eventName
     *
     * @return string
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * Get entity
     *
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Get parentEntity
     *
     * @return object
     */
    public function getParentEntity()
    {
        return $this->parentEntity;
    }
}
