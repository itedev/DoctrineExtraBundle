<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use Doctrine\Common\Persistence\ObjectManager;
use ITE\DoctrineExtraBundle\Dependency\Metadata\ClassMetadata;

/**
 * Interface DependencyMapBuilderInterface
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
interface DependencyMapBuilderInterface
{
    /**
     * @param ObjectManager $manager
     * @return array|ClassMetadata[]
     */
    public function getSortedClassMetadatas(ObjectManager $manager);

    /**
     * @param string $class
     * @return array|ClassMetadata[]
     */
    public function getDependencyClassMetadatas($class);

    /**
     * @param ObjectManager $manager
     */
    public function buildDependencyMap(ObjectManager $manager);
}
