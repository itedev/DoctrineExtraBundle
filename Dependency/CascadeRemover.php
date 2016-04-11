<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;
use Doctrine\ORM\EntityManager;
use ITE\Common\Util\ArrayUtils;
use ITE\DoctrineExtraBundle\Dependency\Metadata\DependencyMetadata;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Class CascadeRemover
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class CascadeRemover implements CascadeRemoverInterface
{
    /**
     * @var ManagerRegistry $registry
     */
    protected $registry;

    /**
     * @var DependencyMapBuilder $dependencyMapBuilder
     */
    protected $dependencyMapBuilder;

    /**
     * @var DependencyMetadataFactory $dependencyMetadataFactory
     */
    protected $dependencyMetadataFactory;

    /**
     * @param ManagerRegistry $registry
     * @param DependencyMapBuilder $dependencyMapBuilder
     * @param DependencyMetadataFactory $dependencyMetadataFactory
     */
    public function __construct(
        ManagerRegistry $registry,
        DependencyMapBuilder $dependencyMapBuilder,
        DependencyMetadataFactory $dependencyMetadataFactory
    ) {
        $this->registry = $registry;
        $this->dependencyMapBuilder = $dependencyMapBuilder;
        $this->dependencyMetadataFactory = $dependencyMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($entity)
    {
        $class = get_class($entity);
        /** @var EntityManager $manager */
        $manager = $this->registry->getManagerForClass($class);
        $doctrineClassMetadata = $manager->getClassMetadata($class);

        // get dependent entity identifiers
        $identifier = $doctrineClassMetadata->getIdentifierValues($entity);
        $identifiers = [$identifier];
        $result = [
            $class => $identifiers,
        ];
        $this->addDependencyIdentifiersRecursive($manager, $doctrineClassMetadata, $identifiers, $result);
        $sortedDoctrineClassMetadatas = $this->dependencyMapBuilder->getSortedClassMetadatas($manager);
        $sortedResult = $this->sortResult($sortedDoctrineClassMetadatas, $result);
        // get dependent many-to-many tables
        $tables = $this->getDependencyManyToManyTables($sortedDoctrineClassMetadatas, $sortedResult);
        // get unidirectional one-to-one entities
        $oneToOneDependencies = $this->getOneToOneDependencies($manager, $sortedDoctrineClassMetadatas, $sortedResult);

        $manager->beginTransaction();
        try {
            foreach ($tables as $table => $relatedClasses) {
                $this->doRemoveManyToManyTable($manager, $table, $relatedClasses, $sortedResult);
            }
            foreach ($sortedResult as $dependencyClass => $dependencyIdentifiers) {
                $this->doRemoveManyToOneEntity($manager, $dependencyClass, $dependencyIdentifiers);
            }
            foreach ($oneToOneDependencies as $class => $identifiers) {
                $this->doRemoveOneToOneEntity($manager, $class, $identifiers);
            }
            $manager->commit();

            return true;
        } catch (\Exception $e) {
            $manager->rollback();
        }

        return false;
    }

    /**
     * @param EntityManager $manager
     * @param DoctrineClassMetadata $doctrineClassMetadata
     * @param array $identifiers
     * @param array $result
     */
    protected function addDependencyIdentifiersRecursive(
        EntityManager $manager,
        DoctrineClassMetadata $doctrineClassMetadata,
        array $identifiers,
        array &$result = []
    ) {
        $class = $doctrineClassMetadata->getName();
        $classMetadata = $this->dependencyMetadataFactory->getMetadataFor($class);
        $dependencyMetadatas = $classMetadata->getDependencies();
        foreach ($dependencyMetadatas as $dependencyMetadata) {
            $dependencyDoctrineClassMetadata = $dependencyMetadata->getClassMetadata();
            $dependencyClass = $dependencyDoctrineClassMetadata->getName();

            $dependencyIdentifiers = $this->getDependencyIdentifiers(
                $manager,
                $dependencyMetadata,
                $identifiers
            );
            $dependencyIdentifiers = ArrayUtils::indexByCallable(
                $dependencyIdentifiers,
                function ($dependencyIdentifier) {
                    return serialize($dependencyIdentifier);
                }
            );

            if (!isset($result[$dependencyClass])) {
                $result[$dependencyClass] = $dependencyIdentifiers;
                if (!empty($dependencyIdentifiers)) {
                    $this->addDependencyIdentifiersRecursive(
                        $manager,
                        $dependencyDoctrineClassMetadata,
                        array_values($dependencyIdentifiers),
                        $result
                    );
                }
            } else {
                $oldDependencyIdentifiers = $result[$dependencyClass];
                $newDependencyIdentifiers = array_merge($result[$dependencyClass], $dependencyIdentifiers);
                $addedDependencyIdentifiers = array_diff_key($newDependencyIdentifiers, $oldDependencyIdentifiers);

                $result[$dependencyClass] = $newDependencyIdentifiers;
                if (!empty($addedDependencyIdentifiers)) {
                    $this->addDependencyIdentifiersRecursive(
                        $manager,
                        $dependencyDoctrineClassMetadata,
                        array_values($addedDependencyIdentifiers),
                        $result
                    );
                }
            }
        }
    }

    /**
     * @param EntityManager $manager
     * @param DependencyMetadata $dependencyMetadata
     * @param array $identifiers
     * @return array
     */
    protected function getDependencyIdentifiers(
        EntityManager $manager,
        DependencyMetadata $dependencyMetadata,
        array $identifiers
    ) {
        $dependencyDoctrineClassMetadata = $dependencyMetadata->getClassMetadata();
        $dependencyClass = $dependencyDoctrineClassMetadata->getName();

        $dependencyIdentifierFieldNames = $dependencyDoctrineClassMetadata->getIdentifierFieldNames();
        $dependencyAssociationNames = $dependencyMetadata->getAssociationNames();

        $alias = 'o';
        $targetIdsParameterName = 'targetIds';
        $qb = $manager->createQueryBuilder();
        foreach ($dependencyIdentifierFieldNames as $dependencyIdentifierFieldName) {
            $qb->addSelect($alias . '.' . $dependencyIdentifierFieldName);
        }
        $qb
            ->from($dependencyClass, $alias)
        ;
        foreach ($dependencyAssociationNames as $dependencyAssociationName) {
            $qb
                ->orWhere($qb->expr()->in(
                    $alias . '.' . $dependencyAssociationName,
                    ':' . $targetIdsParameterName
                ))
            ;
        }

        $limit = 5000;
        $count = count($identifiers);
        $dependencyIdentifiers = [];
        for ($offset = 0; $offset < $count; $offset += $limit) {
            $subIdentifiers = array_slice($identifiers, $offset, $limit);

            $clonedQb = clone $qb;
            $subDependencyIdentifiers = $clonedQb
                ->setParameter(':' . $targetIdsParameterName, $subIdentifiers)
                ->getQuery()
                ->getResult()
            ;
            $dependencyIdentifiers = array_merge($dependencyIdentifiers, $subDependencyIdentifiers);
        }

        return $dependencyIdentifiers;
    }

    /**
     * @param array|DoctrineClassMetadata[] $sortedDoctrineClassMetadatas
     * @param array $result
     * @return array
     */
    protected function sortResult(array $sortedDoctrineClassMetadatas, array $result)
    {
        $sortedResult = [];
        foreach ($sortedDoctrineClassMetadatas as $dependencyClass => $dependencyDoctrineClassMetadata) {
            if (isset($result[$dependencyClass]) && !empty($result[$dependencyClass])) {
                $sortedResult[$dependencyClass] = array_values($result[$dependencyClass]);
            }
        }

        return $sortedResult;
    }

    /**
     * @param array|DoctrineClassMetadata[] $sortedDoctrineClassMetadatas
     * @param array $sortedResult
     * @return array
     */
    protected function getDependencyManyToManyTables(array $sortedDoctrineClassMetadatas, array $sortedResult)
    {
        $classes = array_keys($sortedResult);
        $tables = [];
        foreach ($sortedDoctrineClassMetadatas as $class => $doctrineClassMetadata) {
            foreach ($doctrineClassMetadata->associationMappings as $associationMapping) {
                if ($associationMapping['isOwningSide']
                    && $associationMapping['type'] === DoctrineClassMetadata::MANY_TO_MANY) {
                    $targetClass = $associationMapping['targetEntity'];

                    $hasClass = in_array($class, $classes);
                    $hasTargetClass = in_array($targetClass, $classes);

                    if ($hasClass || $hasTargetClass) {
                        $table = $associationMapping['joinTable']['name'];

                        if (!isset($tables[$table])) {
                            $tables[$table] = [];
                        }
                        if ($hasClass) {
                            $tables[$table][$class] = $associationMapping['relationToSourceKeyColumns'];
                        }
                        if ($hasTargetClass) {
                            $tables[$table][$targetClass] = $associationMapping['relationToTargetKeyColumns'];
                        }
                    }
                }
            }
        }

        return $tables;
    }

    /**
     * @param EntityManager $manager
     * @param array|DoctrineClassMetadata[] $sortedDoctrineClassMetadatas
     * @param array $sortedResult
     * @return array
     */
    protected function getOneToOneDependencies(
        EntityManager $manager,
        array $sortedDoctrineClassMetadatas,
        array $sortedResult
    ) {
        $alias1 = 'o1';
        $alias2 = 'o2';
        $idsParameter = 'ids';
        $limit = 5000;

        $result = [];
        foreach ($sortedResult as $class => $identifiers) {
            $doctrineClassMetadata = $sortedDoctrineClassMetadatas[$class];
            foreach ($doctrineClassMetadata->associationMappings as $associationMapping) {
                if ($associationMapping['isOwningSide']
                    && $associationMapping['type'] === DoctrineClassMetadata::ONE_TO_ONE
                    && ($associationMapping['isCascadeRemove'] || $associationMapping['orphanRemoval'])) {
                    $targetClass = $associationMapping['targetEntity'];

                    $identifiers = $sortedResult[$class];

                    $count = count($identifiers);
                    $targetIdentifiers = [];
                    for ($offset = 0; $offset < $count; $offset += $limit) {
                        $subIdentifiers = array_slice($identifiers, $offset, $limit);

                        $qb = $manager->createQueryBuilder();
                        $subTargetIdentifiers = $qb
                            ->select($alias2 . '.id')
                            ->from($class, $alias1)
                            ->innerJoin($alias1 . '.' . $associationMapping['fieldName'], $alias2)
                            ->where($qb->expr()->in(
                                $alias1 . '.' . 'id',
                                ':' . $idsParameter
                            ))
                            ->setParameter(':' . $idsParameter, $subIdentifiers)
                            ->getQuery()
                            ->getArrayResult()
                        ;

                        $subTargetIdentifiers = ArrayUtils::indexByCallable($subTargetIdentifiers, function ($identifier) {
                            return serialize($identifier);
                        });
                        $targetIdentifiers = array_merge($targetIdentifiers, $subTargetIdentifiers);
                    }

                    if (empty($targetIdentifiers)) {
                        continue;
                    }
                    if (!isset($result[$targetClass])) {
                        $result[$targetClass] = [];
                    }
                    $result[$targetClass] = array_merge($result[$targetClass], $targetIdentifiers);
                }
            }
        }

        $result = array_map(function (array $item) {
            return array_values($item);
        }, $result);

        return $result;
    }

    /**
     * @param EntityManager $manager
     * @param string $class
     * @param array $identifiers
     * @return int
     */
    protected function doRemoveOneToOneEntity(
        EntityManager $manager,
        $class,
        array $identifiers
    ) {
        $alias = 'o';
        $idsParameter = 'ids';
        $limit = 5000;

        $qb = $manager->createQueryBuilder();
        $qb
            ->delete($class, $alias)
            ->where($qb->expr()->in(
                $alias . '.' . 'id',
                ':' . $idsParameter
            ))
        ;

        $removedCount = 0;
        $count = count($identifiers);
        for ($offset = 0; $offset < $count; $offset += $limit) {
            $subIdentifiers = array_slice($identifiers, $offset, $limit);

            $clonedQb = clone $qb;
            $removedCount += $clonedQb
                ->setParameter(':' . $idsParameter, $subIdentifiers)
                ->getQuery()
                ->execute()
            ;
        }

        return $removedCount;
    }

    /**
     * @param EntityManager $manager
     * @param $table
     * @param array $relatedClasses
     * @param array $sortedResult
     * @return int
     */
    protected function doRemoveManyToManyTable(EntityManager $manager, $table, array $relatedClasses, array $sortedResult)
    {
        $limit = 5000;
        $qb = $manager->getConnection()->createQueryBuilder()
            ->delete($table);

        $removedCount = 0;
        foreach ($relatedClasses as $relatedClass => $columns) {
            $identifiers = $sortedResult[$relatedClass];

            $count = count($identifiers);
            for ($offset = 0; $offset < $count; $offset += $limit) {
                $subIdentifiers = array_slice($identifiers, $offset, $limit);

                $clonedQb = clone $qb;
                foreach ($columns as $columnName => $targetColumnName) {
                    $clonedQb
                        ->andWhere($qb->expr()->in(
                            $columnName,
                            array_map(function ($identifier) use ($targetColumnName) {
                                return $identifier[$targetColumnName];
                            }, $subIdentifiers)
                        ))
                    ;
                }
                $removedCount += $clonedQb
                    ->execute()
                ;
            }
        }

        return $removedCount;
    }

    /**
     * @param EntityManager $manager
     * @param string $class
     * @param array $identifiers
     * @return int
     */
    protected function doRemoveManyToOneEntity(EntityManager $manager, $class, array $identifiers)
    {
        $alias = 'o';
        $idsParameter = 'ids';
        $qb = $manager->createQueryBuilder();
        $qb
            ->delete($class, $alias)
            ->where($qb->expr()->in(
                $alias . '.id',
                ':' . $idsParameter
            ))
        ;

        $limit = 5000;
        $count = count($identifiers);
        $removedCount = 0;
        for ($offset = 0; $offset < $count; $offset += $limit) {
            $subIdentifiers = array_slice($identifiers, $offset, $limit);

            $clonedQb = clone $qb;
            $removedCount += $clonedQb
                ->setParameter(':' . $idsParameter, $subIdentifiers)
                ->getQuery()
                ->execute()
            ;
        }

        return $removedCount;
    }
}
