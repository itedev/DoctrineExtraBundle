<?php

namespace ITE\DoctrineExtraBundle\Dependency;

/**
 * Interface DependencyManagerInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DependencyManagerInterface
{
    /**
     * @param object $entity
     * @return void
     */
    public function remove($entity);
}
