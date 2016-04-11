<?php

namespace ITE\DoctrineExtraBundle\Dependency\Counter;

/**
 * Interface EntityCounterInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface EntityCounterInterface
{
    /**
     * @param object $entity
     * @param bool $force
     * @return int
     */
    public function count($entity, $force = false);
}
