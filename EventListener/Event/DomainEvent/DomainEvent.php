<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
use ITE\DoctrineExtraBundle\Exception\InvalidArgumentException;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class DomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DomainEvent extends Event
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
     * @var array $data
     */
    protected $data = [];

    /**
     * @param string $eventName
     * @param array $data
     */
    public function __construct($eventName, array $data = [])
    {
        $this->eventName = $eventName;
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * @return DomainEventAwareInterface
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param DomainEventAwareInterface $entity
     * @return $this
     */
    public function setEntity(DomainEventAwareInterface $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasDataItem($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param string $name
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getDataItem($name, $defaultValue = null)
    {
        return $this->hasDataItem($name) ? $this->data[$name] : $defaultValue;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->hasDataItem($name)) {
            throw new InvalidArgumentException(sprintf('Invalid data item name "%s"', $name));
        }

        return $this->data[$name];
    }
}
