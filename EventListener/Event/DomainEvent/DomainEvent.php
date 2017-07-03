<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;

use ITE\DoctrineExtraBundle\Exception\InvalidArgumentException;

/**
 * Class DomainEvent
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DomainEvent extends AbstractDomainEvent
{
    /**
     * @var array $data
     */
    protected $data = [];

    /**
     * @param string $eventName
     * @param array $data
     * @param bool $grouped
     */
    public function __construct($eventName, array $data = [], $grouped = false)
    {
        parent::__construct($eventName, $grouped);
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasDataItem($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param string $name
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getDataItem($name, $defaultValue = null)
    {
        return $this->hasDataItem($name) ? $this->data[$name] : $defaultValue;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->hasDataItem($name)) {
            throw new InvalidArgumentException(sprintf('Invalid data item name "%s"', $name));
        }

        return $this->data[$name];
    }
}
