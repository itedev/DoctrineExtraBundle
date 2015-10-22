<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event;

use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
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
     * @var DomainEventAwareInterface $subject
     */
    private $subject;

    /**
     * @var array $payload
     */
    private $payload;

    /**
     * @param string $eventName
     * @param array $payload
     */
    public function __construct($eventName, $payload = [])
    {
        $this->eventName = $eventName;
        $this->payload = $payload;
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
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param DomainEventAwareInterface $subject
     * @return $this
     */
    public function setSubject(DomainEventAwareInterface $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->payload)) {
            throw new \RuntimeException(sprintf('Invalid property name "%s"', $name));
        }

        return $this->payload[$name];
    }
}
