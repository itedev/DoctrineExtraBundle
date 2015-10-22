<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

/**
 * Interface DomainEventAwareInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DomainEventAwareInterface
{
    /**
     * @return array|DomainEvent[]
     */
    public function popEvents();
    
    /**
     * @param string $eventName
     * @param array $payload
     */
    public function dispatch($eventName, array $payload = []);
}
