<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Class DependencyMapBuilder
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DependencyMapBuilder implements DependencyMapBuilderInterface
{
    const NOT_VISITED = 1;
    const IN_PROGRESS = 2;
    const VISITED = 3;

    /**
     * @var array
     */
    protected $nodeStates = [];

    /**
     * @var array|ClassMetadata[][]
     */
    protected $classMetadatas = [];

    /**
     * @var array|ClassMetadata[][]
     */
    protected $dependentClassMetadatas = [];

    /**
     * @var array|ClassMetadata[][]
     */
    protected $sortedClassMetadatas = [];

    /**
     * @var ManagerRegistry $registry
     */
    protected $registry;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function getSortedClassMetadatas(ObjectManager $manager)
    {
        $managerId = $this->getManagerId($manager);
        if (!$this->hasDependencyMapForManager($manager)) {
            $this->buildDependencyMap($manager);
        }

        $nodeCount = count($this->classMetadatas[$managerId]);
        if ($nodeCount <= 1) {
            return ($nodeCount == 1) ? array_values($this->classMetadatas[$managerId]) : [];
        }

        foreach ($this->classMetadatas[$managerId] as $node) {
            $this->nodeStates[$managerId][$node->getName()] = self::NOT_VISITED;
        }
        foreach ($this->classMetadatas[$managerId] as $node) {
            if (self::NOT_VISITED === $this->nodeStates[$managerId][$node->getName()]) {
                $this->visitNode($managerId, $node);
            }
        }

        $sortedClassMetadatas = $this->sortedClassMetadatas[$managerId];
        $this->sortedClassMetadatas[$managerId] = [];
        $this->nodeStates[$managerId] = [];

        return $sortedClassMetadatas;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencyClassMetadatas($class)
    {
        $manager = $this->registry->getManagerForClass($class);
        $managerId = $this->getManagerId($manager);
        if (!$this->hasDependencyMapForManager($manager)) {
            $this->buildDependencyMap($manager);
        }

        $dependencyClassMetadatas = isset($this->dependentClassMetadatas[$managerId][$class])
            ? $this->dependentClassMetadatas[$managerId][$class]
            : [];

        return array_filter($dependencyClassMetadatas, function (ClassMetadata $dependencyClassMetadata) {
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $dependencyClassMetadata */
            return $dependencyClassMetadata->isInheritanceTypeNone()
                || in_array($dependencyClassMetadata->name, $dependencyClassMetadata->discriminatorMap);
        });
    }

    /**
     * @param string $managerId
     * @param string $class
     * @return bool
     */
    protected function hasClassMetadata($managerId, $class)
    {
        return isset($this->classMetadatas[$managerId]) && isset($this->classMetadatas[$managerId][$class]);
    }

    /**
     * @param string $managerId
     * @param ClassMetadata $classMetadata
     */
    protected function addClassMetadata($managerId, ClassMetadata $classMetadata)
    {
        $this->classMetadatas[$managerId][$classMetadata->getName()] = $classMetadata;
    }

    /**
     * @param string $managerId
     * @param ClassMetadata $classMetadata
     * @param ClassMetadata $dependencyClassMetadata
     */
    protected function addDependency($managerId, ClassMetadata $classMetadata, ClassMetadata $dependencyClassMetadata)
    {
        $this->dependentClassMetadatas[$managerId][$classMetadata->getName()][$dependencyClassMetadata->getName()] = $dependencyClassMetadata;
    }

    /**
     * @param string $managerId
     * @param ClassMetadata $node
     */
    protected function visitNode($managerId, ClassMetadata $node)
    {
        $this->nodeStates[$managerId][$node->getName()] = self::IN_PROGRESS;
        if (isset($this->dependentClassMetadatas[$managerId])
            && isset($this->dependentClassMetadatas[$managerId][$node->getName()])) {
            foreach ($this->dependentClassMetadatas[$managerId][$node->getName()] as $dependentNode) {
                if (self::NOT_VISITED === $this->nodeStates[$managerId][$dependentNode->getName()]) {
                    $this->visitNode($managerId, $dependentNode);
                }
            }
        }

        $this->nodeStates[$managerId][$node->getName()] = self::VISITED;
        $this->sortedClassMetadatas[$managerId][$node->getName()] = $node;
    }

    /**
     * @param ObjectManager $manager
     * @return bool
     */
    protected function hasDependencyMapForManager($manager)
    {
        $managerId = $this->getManagerId($manager);

        return isset($this->classMetadatas[$managerId]);
    }

    /**
     * @param ObjectManager $manager
     * @return string
     */
    protected function getManagerId(ObjectManager $manager)
    {
        return spl_object_hash($manager);
    }

    /**
     * {@inheritdoc}
     */
    public function buildDependencyMap(ObjectManager $manager)
    {
        if ($this->hasDependencyMapForManager($manager)) {
            return;
        }

        $managerId = $this->getManagerId($manager);
        $classMetadatas = $manager->getMetadataFactory()->getAllMetadata();
        foreach ($classMetadatas as $classMetadata) {
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $classMetadata */
            if ($classMetadata->isMappedSuperclass
                || (isset($classMetadata->isEmbeddedClass) && $classMetadata->isEmbeddedClass)) {
                continue;
            }

            $this->addClassMetadata($managerId, $classMetadata);

            foreach ($classMetadata->parentClasses as $parentClass) {
                $parentClassMetadata = $manager->getClassMetadata($parentClass);

                if (!$this->hasClassMetadata($managerId, $parentClassMetadata->getName())) {
                    $this->addClassMetadata($managerId, $parentClassMetadata);
                }

                if ($classMetadata->isInheritanceTypeJoined()) {
                    $this->addDependency($managerId, $classMetadata, $parentClassMetadata);
                }
                //$this->addDependency($managerId, $parentClassMetadata, $classMetadata);
            }

            foreach ($classMetadata->associationMappings as $associationMapping) {
                if ($associationMapping['isOwningSide']
                    && $associationMapping['type'] !== \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY) {
                    /** @var \Doctrine\ORM\Mapping\ClassMetadata $targetClassMetadata */
                    $targetClassMetadata = $manager->getClassMetadata($associationMapping['targetEntity']);

                    if (!$this->hasClassMetadata($managerId, $targetClassMetadata->getName())) {
                        $this->addClassMetadata($managerId, $targetClassMetadata);
                    }
                    $this->addDependency($managerId, $targetClassMetadata, $classMetadata);

                    foreach ($targetClassMetadata->parentClasses as $targetParentClass) {
                        $targetParentClassMetadata = $manager->getClassMetadata($targetParentClass);

                        if (!$this->hasClassMetadata($managerId, $targetParentClassMetadata->getName())) {
                            $this->addClassMetadata($managerId, $targetParentClassMetadata);
                        }
                        $this->addDependency($managerId, $targetParentClassMetadata, $classMetadata);
                    }

                    foreach ($targetClassMetadata->subClasses as $targetSubClass) {
                        $targetSubClassMetadata = $manager->getClassMetadata($targetSubClass);

                        if (!$this->hasClassMetadata($managerId, $targetSubClassMetadata->getName())) {
                            $this->addClassMetadata($managerId, $targetSubClassMetadata);
                        }
                        $this->addDependency($managerId, $targetSubClassMetadata, $classMetadata);
                    }
                }
            }
        }
    }
}