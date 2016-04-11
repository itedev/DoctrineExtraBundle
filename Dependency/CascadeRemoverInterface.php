<?php

namespace ITE\DoctrineExtraBundle\Dependency;

/**
 * Interface CascadeRemoverInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface CascadeRemoverInterface
{
    /**
     * @param object $entity
     * @return bool
     */
    public function remove($entity);
}

