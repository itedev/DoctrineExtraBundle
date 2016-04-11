<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use ITE\DoctrineExtraBundle\Dependency\Metadata\ClassMetadata;

/**
 * Interface DependencyMetadataFactoryInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DependencyMetadataFactoryInterface
{
    /**
     * @param string|object $class
     * @return ClassMetadata
     */
    public function getMetadataFor($class);
}

