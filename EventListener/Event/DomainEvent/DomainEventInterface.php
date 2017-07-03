<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;

/**
 * Interface DomainEventInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DomainEventInterface
{
    /**
     * @return string
     */
    public function getEventName();

    /**
     * @return DomainEventAwareInterface
     */
    public function getEntity();

    /**
     * @param DomainEventAwareInterface $entity
     * @return $this
     */
    public function setEntity(DomainEventAwareInterface $entity);

    /**
     * @return boolean
     */
    public function isGrouped();
}
