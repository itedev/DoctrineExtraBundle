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
 * Class DependencyManager
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DependencyManager implements DependencyManagerInterface
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
        $sortedDoctrineClassMetadatas = $this->dependencyMapBuilder->getSortedClassMetadatas($manager);

        $oneToManyDependencies = [];
        $oneToOneDependencies = [];
        $this->findDependenciesRecursive(
            $manager,
            $sortedDoctrineClassMetadatas,
            [
                $class => $identifiers,
            ],
            $oneToManyDependencies,
            $oneToOneDependencies,
            true,
            $options
        );
        $oneToManyDependencies = $this->sortDependencies(
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );
        $oneToOneDependencies = $this->sortDependencies(
            $sortedDoctrineClassMetadatas,
            $oneToOneDependencies
        );
        $manyToManyTables = $this->getDependencyManyToManyTables(
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies,
            $options
        );

        return [
            'one-to-many' => $oneToManyDependencies,
            'many-to-many' => $manyToManyTables,
            'one-to-one' => $oneToOneDependencies,
        ];
    }

    /**
     * @param EntityManager $manager
     * @param array $sortedDoctrineClassMetadatas
     * @param array $dependencies
     * @param array $globalOneToManyDependencies
     * @param array $globalOneToOneDependencies
     * @param bool $root
     * @param array $options
     */
    protected function findDependenciesRecursive(
        EntityManager $manager,
        array $sortedDoctrineClassMetadatas,
        array $dependencies = [],
        array &$globalOneToManyDependencies = [],
        array &$globalOneToOneDependencies = [],
        $root = false,
        array $options = []
    ) {
        $oneToManyDependencies = $root ? $dependencies : [];
        foreach ($dependencies as $class => $identifiers) {
            $doctrineClassMetadata = $sortedDoctrineClassMetadatas[$class];

            $this->addDependencyIdentifiersRecursive(
                $manager,
                $doctrineClassMetadata,
                array_values($identifiers),
                $oneToManyDependencies,
                $options
            );
        }

        $oneToManyDependencies = $this->sortDependencies(
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );
        $oneToOneDependencies = $this->getOneToOneDependencies(
            $manager,
            $sortedDoctrineClassMetadatas,
            $oneToManyDependencies
        );

        $globalOneToManyDependencies = array_merge($globalOneToManyDependencies, $oneToManyDependencies);
        $globalOneToOneDependencies = array_merge($globalOneToOneDependencies, $oneToOneDependencies);

        $options['excluded_classes'] = array_unique(array_merge($options['excluded_classes'], array_keys($oneToManyDependencies)));

        if (!empty($oneToManyDependencies)) {
            $this->findDependenciesRecursive(
                $manager,
                $sortedDoctrineClassMetadatas,
                $oneToOneDependencies,
                $globalOneToManyDependencies,
                $globalOneToOneDependencies,
                false, // no root
                $options
            );
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'excluded_classes' => [],
            'dependency_association_modifier' => null,
            'identifier_query_builder_modifier' => null,
        ]);
        $resolver->setAllowedTypes([
            'excluded_classes' => 'array',
            'dependency_association_modifier' => ['null', 'callable'],
            'identifier_query_builder_modifier' => ['null', 'callable'],
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
     * @param string $class
     * @param bool|null $optional
     * @param bool $recursive
     */
    public function getDependentClasses($class, $optional = null, $recursive = false)
    {
        $classMetadata = $this->dependencyMetadataFactory->getMetadataFor($class);
        $dependencyMetadatas = $classMetadata->getDependencies();

        $classes = [];
        foreach ($dependencyMetadatas as $dependencyMetadata) {
            $dependencyDoctrineClassMetadata = $dependencyMetadata->getClassMetadata();
            $dependencyClass = $dependencyDoctrineClassMetadata->getName();

            if (null === $optional || $optional === $dependencyMetadata->isDoctrineNullable()) {
                if (!array_key_exists($dependencyClass, $classes)) {
                    $classes[$dependencyClass] = [];
                }
                $classes[$dependencyClass][] = $dependencyMetadata;

                if ($recursive) {
                    $classes = array_merge_recursive($classes, $this->getDependentClasses($dependencyClass, $optional, $recursive));
                }
            }
        }

        return $classes;
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
                $identifiers,
                $options
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
     * @param array $options
     * @return array
     */
    protected function getDependencyIdentifiers(
        EntityManager $manager,
        DependencyMetadata $dependencyMetadata,
        array $identifiers,
        array $options = []
    ) {
        $dependencyDoctrineClassMetadata = $dependencyMetadata->getClassMetadata();
        $dependencyClass = $dependencyDoctrineClassMetadata->getName();

        $dependencyIdentifierFieldNames = $dependencyDoctrineClassMetadata->getIdentifierFieldNames();
        $dependencyAssociationNames = $dependencyMetadata->getAssociationNames();

        if (is_callable($options['dependency_association_modifier'])) {
            if (null !== $result = call_user_func_array(
                    $options['dependency_association_modifier'],
                    [$dependencyAssociationNames, $dependencyMetadata]
                )) {
                $dependencyAssociationNames = $result;
            }
        }
        if (empty($dependencyAssociationNames)) {
            return [];
        }

        $alias = 'o';
        $targetIdsParameterName = 'targetIds';
        $qb = $manager->createQueryBuilder();
        foreach ($dependencyIdentifierFieldNames as $dependencyIdentifierFieldName) {
            $qb->addSelect($alias . '.' . $dependencyIdentifierFieldName);
        }
        $qb
            ->from($dependencyClass, $alias)
        ;
        if (!$dependencyDoctrineClassMetadata->isInheritanceTypeNone()) {
            $qb
                ->andWhere(sprintf('%s INSTANCE OF %s', $alias, $dependencyClass))
            ;
        }
        $orX = $qb->expr()->orX();
        foreach ($dependencyAssociationNames as $dependencyAssociationName) {
            $orX->add($qb->expr()->in(
                $alias . '.' . $dependencyAssociationName,
                ':' . $targetIdsParameterName
            ));
        }
        $qb->andWhere($orX);

        if (is_callable($options['identifier_query_builder_modifier'])) {
            if (false === call_user_func_array($options['identifier_query_builder_modifier'], [$dependencyMetadata, $qb])) {
                return [];
            }
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
     * @param array $dependencies
     * @return array
     */
    protected function sortDependencies(array $sortedDoctrineClassMetadatas, array $dependencies)
    {
        $sortedDependencies = [];
        foreach ($sortedDoctrineClassMetadatas as $dependencyClass => $dependencyDoctrineClassMetadata) {
            if (isset($dependencies[$dependencyClass]) && !empty($dependencies[$dependencyClass])) {
                $sortedDependencies[$dependencyClass] = array_values($dependencies[$dependencyClass]);
            }
        }

        return $sortedDependencies;
    }

    /**
     * @param array|DoctrineClassMetadata[] $sortedDoctrineClassMetadatas
     * @param array $oneToManyDependencies
     * @param array $options
     * @return array
     */
    protected function getDependencyManyToManyTables(
        array $sortedDoctrineClassMetadatas,
        array $oneToManyDependencies,
        array $options = []
    ) {
        $classes = array_keys($oneToManyDependencies);
        $tables = [];
        foreach ($sortedDoctrineClassMetadatas as $class => $doctrineClassMetadata) {
            foreach ($doctrineClassMetadata->associationMappings as $associationMapping) {
                if ($associationMapping['isOwningSide']
                    && $associationMapping['type'] === DoctrineClassMetadata::MANY_TO_MANY) {
                    $targetClass = $associationMapping['targetEntity'];

                    if (in_array($class, $options['excluded_classes'])
                        || in_array($targetClass, $options['excluded_classes'])) {
                        continue;
                    }

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
        $limit = 500;
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

        $limit = 500;
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
        $limit = 500;

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
