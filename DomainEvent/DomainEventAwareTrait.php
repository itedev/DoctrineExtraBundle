<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

/**
 * Class DomainEventAwareTrait
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
trait DomainEventAwareTrait
{
    /**
     * @var array|DomainEvent[] $events
     */
    private $events = [];

    /**
     * @return array|DomainEvent[]
     */
    public function popEvents()
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * @param $eventName
     * @param array $payload
     */
    public function dispatch($eventName, array $payload = [])
    {
        $this->events[] = new DomainEvent($eventName, $payload);
    }
}
