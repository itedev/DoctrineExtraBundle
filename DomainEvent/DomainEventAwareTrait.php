<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEvent;
use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEventInterface;
use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\FieldChangeDomainEvent;
use ITE\DoctrineExtraBundle\Exception\UnexpectedTypeException;

/**
 * Class DomainEventAwareTrait
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
trait DomainEventAwareTrait
{
    /**
     * @var array|DomainEventInterface[] $domainEvents
     */
    private $domainEvents = [];

    /**
     * {@inheritdoc}
     */
    public function popDomainEvents()
    {
        $domainEvents = $this->domainEvents;
        $this->domainEvents = [];

        return $domainEvents;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatchDomainEvent($eventName, array $payload = [], $grouped = false)
    {
        switch (true) {
            case is_string($eventName):
                $domainEvent = new DomainEvent($eventName, $payload, $grouped);
                break;
            case $eventName instanceof DomainEventInterface:
                $domainEvent = $eventName;
                break;
            default:
                throw new UnexpectedTypeException(
                    $eventName,
                    'ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEventInterface or string'
                );
        }

        $this->domainEvents[] = $domainEvent;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatchFieldChangeDomainEvent($eventName, $oldValue, $newValue)
    {
        if (!is_string($eventName)) {
            throw new UnexpectedTypeException(
                $eventName,
                'string'
            );
        }

        $domainEvent = new FieldChangeDomainEvent($eventName, $oldValue, $newValue);
        $this->domainEvents[] = $domainEvent;
    }
}
