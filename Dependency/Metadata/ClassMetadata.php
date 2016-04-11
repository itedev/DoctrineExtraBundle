<?php

namespace ITE\DoctrineExtraBundle\Dependency\Metadata;

/**
 * Class ClassMetadata
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class ClassMetadata
{
    /**
     * @var array|DependencyMetadata[] $dependencies
     */
    private $dependencies;

    /**
     * @param array $dependencies
     */
    public function __construct(array $dependencies)
    {
        $this->dependencies = $dependencies;
    }

    /**
     * Get dependencies

     *
     * @return array|DependencyMetadata[]
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * @param callable $callback
     * @return array|DependencyMetadata[]
     */
    public function filterDependencies($callback)
    {
        return array_filter($this->dependencies, $callback);
    }
}
