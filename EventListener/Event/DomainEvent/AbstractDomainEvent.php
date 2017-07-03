<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class AbstractDomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
abstract class AbstractDomainEvent extends Event implements DomainEventInterface
{
    /**
     * @var string $eventName
     */
    private $eventName;

    /**
     * @var DomainEventAwareInterface $entity
     */
    private $entity;

    /**
     * @var bool $grouped
     */
    private $grouped = false;

    /**
     * @param string $eventName
     * @param bool $grouped
     */
    public function __construct($eventName, $grouped = false)
    {
        $this->eventName = $eventName;
        $this->grouped = $grouped;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * {@inheritdoc}
     */
    public function setEntity(DomainEventAwareInterface $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isGrouped()
    {
        return $this->grouped;
    }
}
