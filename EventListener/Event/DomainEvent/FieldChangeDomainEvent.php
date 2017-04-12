<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

/**
 * Class FieldChangeDomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class FieldChangeDomainEvent extends DomainEvent
{
    /**
     * @param string $eventName
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    public function __construct($eventName, $oldValue, $newValue)
    {
        parent::__construct($eventName, [
            'old' => $oldValue,
            'new' => $newValue,
        ]);
    }

    /**
     * @return mixed
     */
    public function getOldValue()
    {
        return $this->data['old'];
    }

    /**
     * @return mixed
     */
    public function getNewValue()
    {
        return $this->data['new'];
    }
}
