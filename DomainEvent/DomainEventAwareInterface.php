<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEventInterface;

/**
 * Interface DomainEventAwareInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DomainEventAwareInterface
{
    /**
     * @return array|DomainEventInterface[]
     */
    public function popDomainEvents();

    /**
     * @param string|DomainEventInterface $eventName
     * @param array $payload
     * @param bool $grouped
     */
    public function dispatchDomainEvent($eventName, array $payload = [], $grouped = false);
}
