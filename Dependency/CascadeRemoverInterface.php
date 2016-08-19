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
     * @return void
     */
    public function remove($entity);
}
