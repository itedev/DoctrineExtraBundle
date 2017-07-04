<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ArrayIterator;
use ITE\DoctrineExtraBundle\Exception\InvalidArgumentException;
use ITE\DoctrineExtraBundle\Exception\UnexpectedTypeException;

/**
 * Class BatchDomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class BatchDomainEvent extends AbstractDomainEvent implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var array|DomainEvent[] $events
     */
    private $events = [];

    /**
     * @param string $eventName
     * @param array|DomainEvent[] $events
     */
    public function __construct($eventName, array $events = [])
    {
        parent::__construct($eventName, true);

        foreach ($events as $event) {
            if (!$event instanceof DomainEvent) {
                throw new UnexpectedTypeException($event, 'ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEvent');
            }
            $this->addEvent($event);
        }
    }

    /**
     * @param DomainEvent $event
     * @return $this
     */
    public function addEvent(DomainEvent $event)
    {
        if ($event->getEventName() !== $this->getEventName()) {
            throw new InvalidArgumentException(sprintf(
                'Expected event name "%s", "%s" given',
                $this->getEventName(),
                $event->getEventName()
            ));
        }
        $this->events[] = $event;

        return $this;
    }

    /**
     * @return array|DomainEvent[]
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->events);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($index)
    {
        return array_key_exists($index, $this->events);
    }

    /**
     * @param int $index
     * @return DomainEvent
     */
    public function offsetGet($index)
    {
        return $this->events[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($rowIndex, $value)
    {
        throw new \Exception();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($index)
    {
        unset($this->events[$index]);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->events);
    }
}
