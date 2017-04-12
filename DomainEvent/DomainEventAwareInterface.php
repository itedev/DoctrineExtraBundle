<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEvent;

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
    public function popDomainEvents();
    
    /**
     * @param string|DomainEvent $eventName
     * @param array $payload
     */
    public function dispatchDomainEvent($eventName, array $payload = []);
}
