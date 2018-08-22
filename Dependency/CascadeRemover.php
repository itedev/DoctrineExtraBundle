<?php

namespace ITE\DoctrineExtraBundle\Dependency;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;
use Doctrine\ORM\EntityManager;
use ITE\Common\Util\ArrayUtils;
use ITE\DoctrineExtraBundle\Dependency\Metadata\DependencyMetadata;
use ITE\DoctrineExtraBundle\EventListener\Event\CascadeRemoveEvent;
use ITE\DoctrineExtraBundle\EventListener\Event\CascadeRemoveEvents;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
     * @var EventDispatcherInterface $dispatcher
     */
    protected $dispatcher;

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
     * @param EventDispatcherInterface $dispatcher
     * @param DependencyMapBuilder $dependencyMapBuilder
     * @param DependencyMetadataFactory $dependencyMetadataFactory
     */
    public function __construct(
        ManagerRegistry $registry,
        EventDispatcherInterface $dispatcher,
        DependencyMapBuilder $dependencyMapBuilder,
        DependencyMetadataFactory $dependencyMetadataFactory
    ) {
        $this->registry = $registry;
        $this->dispatcher = $dispatcher;
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

        $dependencies = $this->getDependencies($entity);
        $oneToManyDependencies = $dependencies['one-to-many'];
        $manyToManyTables = $dependencies['many-to-many'];
        $oneToOneDependencies = $dependencies['one-to-one'];

        $manager->beginTransaction();
        try {
            foreach ($manyToManyTables as $manyToManyTable => $manyToManyTableData) {
                $this->doRemoveManyToManyTable($manager, $manyToManyTable, $manyToManyTableData['classes'], $oneToManyDependencies);
            }
            foreach ($oneToManyDependencies as $dependencyClass => $dependencyIdentifiers) {
                $this->doRemoveOneToManyEntity($manager, $entity, $dependencyClass, $dependencyIdentifiers);
            }
            foreach ($oneToOneDependencies as $dependencyClass => $dependencyIdentifiers) {
                $this->doRemoveOneToOneEntity($manager, $entity, $dependencyClass, $dependencyIdentifiers);
            }
            $manager->commit();
        } catch (\Exception $e) {
            $manager->rollback();

            throw $e;
        }
    }

    /**
     * @param object $entity
     * @param array $options
     * @return array
     */
    public function getDependencies($entity, array $options = [])
    {
        $options = $this->resolveOptions($options);

        $class = ClassUtils::getRealClass(get_class($entity));
        /** @var EntityManager $manager */
        $manager = $this->registry->getManagerForClass($class);
        $doctrineClassMetadata = $manager->getClassMetadata($class);

        // get dependent entity identifiers
        $identifier = $doctrineClassMetadata->getIdentifierValues($entity);
        $identifiers = [
            serialize($identifier) => $identifier,
        ];
        $oneToManyDependencies = [
            $class => $identifiers,
        ];
        $this->addDependencyIdentifiersRecursive(
            $manager,
            $doctrineClassMetadata,
            array_values($identifiers),
            $oneToManyDependencies,
            $options
        );
        $sortedDoctrineClassMetadatas = $this->dependencyMapBuilder->getSortedClassMetadatas($manager);
        $oneToManyDependencies = $this->sortOneToManyDependencies(
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );
        // get dependent many-to-many tables
        $manyToManyTables = $this->getDependencyManyToManyTables(
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );
        // get unidirectional one-to-one entities
        $oneToOneDependencies = $this->getOneToOneDependencies(
            $manager,
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );

        return [
            'one-to-many' => $oneToManyDependencies,
            'many-to-many' => $manyToManyTables,
            'one-to-one' => $oneToOneDependencies,
        ];
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'excluded_classes' => [],
        ]);
        $resolver->setAllowedTypes([
            'excluded_classes' => 'array',
        ]);
    }

    /**
     * @param array $options
     * @return array
     */
    protected function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        return $resolver->resolve($options);
    }

    /**
     * @param EntityManager $manager
     * @param DoctrineClassMetadata $doctrineClassMetadata
     * @param array $identifiers
     * @param array $oneToManyDependencies
     * @param array $options
     */
    protected function addDependencyIdentifiersRecursive(
        EntityManager $manager,
        DoctrineClassMetadata $doctrineClassMetadata,
        array $identifiers,
        array &$oneToManyDependencies = [],
        array $options = []
    ) {
        $class = $doctrineClassMetadata->getName();
        $classMetadata = $this->dependencyMetadataFactory->getMetadataFor($class);
        $dependencyMetadatas = $classMetadata->getDependencies();
        foreach ($dependencyMetadatas as $dependencyMetadata) {
            $dependencyDoctrineClassMetadata = $dependencyMetadata->getClassMetadata();
            $dependencyClass = $dependencyDoctrineClassMetadata->getName();

            if (in_array($dependencyClass, $options['excluded_classes'])) {
                continue;
            }

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

            if (!isset($oneToManyDependencies[$dependencyClass])) {
                $oneToManyDependencies[$dependencyClass] = $dependencyIdentifiers;
                if (!empty($dependencyIdentifiers)) {
                    $this->addDependencyIdentifiersRecursive(
                        $manager,
                        $dependencyDoctrineClassMetadata,
                        array_values($dependencyIdentifiers),
                        $oneToManyDependencies,
                        $options
                    );
                }
            } else {
                $oldDependencyIdentifiers = $oneToManyDependencies[$dependencyClass];
                $newDependencyIdentifiers = array_merge($oneToManyDependencies[$dependencyClass], $dependencyIdentifiers);
                $addedDependencyIdentifiers = array_diff_key($newDependencyIdentifiers, $oldDependencyIdentifiers);

                $oneToManyDependencies[$dependencyClass] = $newDependencyIdentifiers;
                if (!empty($addedDependencyIdentifiers)) {
                    $this->addDependencyIdentifiersRecursive(
                        $manager,
                        $dependencyDoctrineClassMetadata,
                        array_values($addedDependencyIdentifiers),
                        $oneToManyDependencies,
                        $options
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
     * @param array $oneToManyDependencies
     * @return array
     */
    protected function sortOneToManyDependencies(array $sortedDoctrineClassMetadatas, array $oneToManyDependencies)
    {
        $sortedOneToManyDependencies = [];
        foreach ($sortedDoctrineClassMetadatas as $dependencyClass => $dependencyDoctrineClassMetadata) {
            if (isset($oneToManyDependencies[$dependencyClass]) && !empty($oneToManyDependencies[$dependencyClass])) {
                $sortedOneToManyDependencies[$dependencyClass] = array_values($oneToManyDependencies[$dependencyClass]);
            }
        }

        return $sortedOneToManyDependencies;
    }

    /**
     * @param array|DoctrineClassMetadata[] $sortedDoctrineClassMetadatas
     * @param array $oneToManyDependencies
     * @return array
     */
    protected function getDependencyManyToManyTables(array $sortedDoctrineClassMetadatas, array $oneToManyDependencies)
    {
        $classes = array_keys($oneToManyDependencies);
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
                            $tables[$table] = [
                                'mapping' => $associationMapping,
                                'classes' => [],
                            ];
                        }
                        if ($hasClass) {
                            $tables[$table]['classes'][$class] = $associationMapping['relationToSourceKeyColumns'];
                        }
                        if ($hasTargetClass) {
                            $tables[$table]['classes'][$targetClass] = $associationMapping['relationToTargetKeyColumns'];
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
     * @param array $oneToManyDependencies
     * @return array
     */
    protected function getOneToOneDependencies(
        EntityManager $manager,
        array $sortedDoctrineClassMetadatas,
        array $oneToManyDependencies
    ) {
        $alias1 = 'o1';
        $alias2 = 'o2';
        $idsParameter = 'ids';
        $limit = 5000;

        $oneToOneDependencies = [];
        foreach ($oneToManyDependencies as $class => $identifiers) {
            $doctrineClassMetadata = $sortedDoctrineClassMetadatas[$class];
            foreach ($doctrineClassMetadata->associationMappings as $associationMapping) {
                if ($associationMapping['isOwningSide']
                    && $associationMapping['type'] === DoctrineClassMetadata::ONE_TO_ONE
                    && ($associationMapping['isCascadeRemove'] || $associationMapping['orphanRemoval'])) {
                    $targetClass = $associationMapping['targetEntity'];

                    $identifiers = $oneToManyDependencies[$class];

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
                    if (!isset($oneToOneDependencies[$targetClass])) {
                        $oneToOneDependencies[$targetClass] = [];
                    }
                    $oneToOneDependencies[$targetClass] = array_merge(
                        $oneToOneDependencies[$targetClass],
                        $targetIdentifiers
                    );
                }
            }
        }

        $oneToOneDependencies = array_map(function (array $item) {
            return array_values($item);
        }, $oneToOneDependencies);

        return $oneToOneDependencies;
    }

    /**
     * @param EntityManager $manager
     * @param $table
     * @param array $relatedClasses
     * @param array $oneToManyDependencies
     * @return int
     */
    protected function doRemoveManyToManyTable(
        EntityManager $manager,
        $table,
        array $relatedClasses,
        array $oneToManyDependencies
    ) {
        $limit = 5000;
        $qb = $manager->getConnection()->createQueryBuilder()
            ->delete($table);

        $removedCount = 0;
        foreach ($relatedClasses as $relatedClass => $columns) {
            $identifiers = $oneToManyDependencies[$relatedClass];

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
     * @param object $rootEntity
     * @param string $class
     * @param array $identifiers
     * @return int
     */
    protected function doRemoveOneToManyEntity(EntityManager $manager, $rootEntity, $class, array $identifiers)
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

        $event = new CascadeRemoveEvent(
            $manager,
            $rootEntity,
            $class,
            $identifiers,
            DoctrineClassMetadata::ONE_TO_MANY
        );
        $this->dispatcher->dispatch(CascadeRemoveEvents::PRE_REMOVE, $event);

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

        $this->dispatcher->dispatch(CascadeRemoveEvents::POST_REMOVE, $event);

        return $removedCount;
    }

    /**
     * @param EntityManager $manager
     * @param object $rootEntity
     * @param string $class
     * @param array $identifiers
     * @return int
     */
    protected function doRemoveOneToOneEntity(
        EntityManager $manager,
        $rootEntity,
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

        $event = new CascadeRemoveEvent(
            $manager,
            $rootEntity,
            $class,
            $identifiers,
            DoctrineClassMetadata::ONE_TO_ONE
        );
        $this->dispatcher->dispatch(CascadeRemoveEvents::PRE_REMOVE, $event);

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

        $this->dispatcher->dispatch(CascadeRemoveEvents::POST_REMOVE, $event);

        return $removedCount;
    }
}
