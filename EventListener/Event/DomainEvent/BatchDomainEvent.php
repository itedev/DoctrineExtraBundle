<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ITE\DoctrineExtraBundle\Exception\InvalidArgumentException;
use ITE\DoctrineExtraBundle\Exception\UnexpectedTypeException;

/**
 * Class BatchDomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class BatchDomainEvent extends AbstractDomainEvent
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
}
