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
    private $targetEntity;

    /**
     * CascadeEvent constructor.
     *
     * @param string $eventName
     * @param object $entity
     * @param object $targetEntity
     */
    public function __construct($eventName, $entity, $targetEntity)
    {
        $this->eventName    = $eventName;
        $this->entity       = $entity;
        $this->targetEntity = $targetEntity;
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
     * Get targetEntity
     *
     * @return object
     */
    public function getTargetEntity()
    {
        return $this->targetEntity;
    }
}
