<?php

namespace ITE\DoctrineExtraBundle\DomainEvent;

/**
 * Class DomainEventAware
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DomainEventAware implements DomainEventAwareInterface
{
    use DomainEventAwareTrait;
}
