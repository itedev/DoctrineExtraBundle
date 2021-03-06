<?php

namespace ITE\DoctrineExtraBundle\ORM\Query;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * Class ResultSetMappingBuilder
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class ResultSetMappingBuilder extends ResultSetMapping
{
    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * @var array $columnTableMap
     */
    protected $columnTableMap = [];

    /**
     * @var array $columnAliasMap
     */
    protected $columnAliasMap = [];

    /**
     * @var array $tableAliasMap
     */
    protected $tableAliasMap = [];

    /**
     * @var array $aliases
     */
    protected $aliases = [];

    /**
     * @var int $columnAliasCounter
     */
    private $columnAliasCounter = 0;

    /**
     * @var int $tableAliasCounter
     */
    private $tableAliasCounter = 0;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param string $columnAlias
     * @param string $tableAlias
     * @param string $columnName
     */
    public function addColumnAlias($columnAlias, $tableAlias, $columnName)
    {
        $this->columnTableMap[$columnAlias] = $tableAlias;
        $this->columnAliasMap[$columnAlias] = $tableAlias . '.' . $columnName;
    }

    /**
     * @param string $tableName
     * @param string $tableAlias
     */
    public function addTableAlias($tableName, $tableAlias)
    {
        $this->tableAliasMap[$tableName] = $tableAlias;
    }

    /**
     * @param string $sql
     * @param string $fullyQualifiedColumnName
     * @return string
     */
    public function generatedColumnAlias($fullyQualifiedColumnName, $sql = null)
    {
        $sql = $sql ? : $fullyQualifiedColumnName;

        if (false === $columnAlias = array_search($fullyQualifiedColumnName, $this->columnAliasMap)) {
            return sprintf('%s AS %s', $sql, $fullyQualifiedColumnName);
        }

        return sprintf('%s AS %s', $sql, $columnAlias);
    }

    /**
     * @return array
     */
    public function getColumnAliasMap()
    {
        return $this->columnAliasMap;
    }

    /**
     * @param string $class
     * @param string $alias
     */
    public function addRootEntityFromClassMetadata($class, $alias)
    {
        $this->addEntityResult($class, $alias);
        $this->addAllClassFields($class, $alias);
    }

    /**
     * @param string $class
     * @param string $alias
     * @param string $parentAlias
     * @param string $relation
     */
    public function addJoinedEntityFromClassMetadata($class, $alias, $parentAlias, $relation)
    {
        $this->addJoinedEntityResult($class, $alias, $parentAlias, $relation);
        $this->addAllClassFields($class, $alias);
    }

    /**
     * @param string $class
     * @param string $alias
     * @param string $parentAlias
     */
    public function addInheritedEntityFromClassMetadata($class, $alias, $parentAlias)
    {
        $classMetadata = $this->em->getClassMetadata($class);
        $platform      = $this->em->getConnection()->getDatabasePlatform();

        $columnName = $classMetadata->discriminatorColumn['name'];
        $columnAlias = $platform->getSQLResultCasing($this->getColumnAlias($columnName));

        $this->setDiscriminatorColumn($parentAlias, $columnAlias);
        $this->addMetaResult($parentAlias, $columnAlias, $columnName);
        $this->addColumnAlias($columnAlias, $alias, $columnName);

        $this->addAllClassFields($class, $alias, $parentAlias);
    }

    /**
     * @param string $className
     * @param string $tableAlias
     * @param array $subTableAliases
     */
    public function addRootInheritedEntityFromClassMetadata($className, $tableAlias, array $subTableAliases = [])
    {
        $classMetadata = $this->em->getClassMetadata($className);
        $platform = $this->em->getConnection()->getDatabasePlatform();

        $this->addEntityResult($className, $tableAlias);
        $this->addAllClassFields($className, $tableAlias);

        // discriminator
        $columnName = $classMetadata->discriminatorColumn['name'];
        $columnAlias = $platform->getSQLResultCasing($this->getColumnAlias($columnName));

        $this->setDiscriminatorColumn($tableAlias, $columnAlias);
        $this->addMetaResult($tableAlias, $columnAlias, $columnName);
        $this->addColumnAlias($columnAlias, $tableAlias, $columnName);

        // sub-classes
        foreach ($classMetadata->subClasses as $subClassName) {
            $subClassMetadata = $this->em->getClassMetadata($subClassName);
            $subTableName = $subClassMetadata->getTableName();

            $subTableAlias = isset($subTableAliases[$subTableName])
                ? $subTableAliases[$subTableName]
                : $this->getTableAlias($subTableName);

            $this->addTableAlias($subTableName, $subTableAlias);
            $this->addAllClassFields($subClassName, $subTableAlias, $tableAlias);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addScalarResult($columnName, $alias = null, $type = 'string')
    {
        $alias = $alias ? : $columnName;

        parent::addScalarResult($columnName, $alias, $type);
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return array_values($this->aliases);
    }

    /**
     * @param $alias
     * @return string
     */
    public function generateEntitySelectClause($alias)
    {
        $sql = '';

        foreach ($this->columnTableMap as $columnName => $tableAlias) {
            if ($alias !== $tableAlias) {
                continue;
            }

            if ($sql) {
                $sql .= ', ';
            }

            $sql .= $alias . '.';

            if (isset($this->fieldMappings[$columnName])) {
                $class = $this->em->getClassMetadata($this->declaringClasses[$columnName]);
                $sql  .= $class->fieldMappings[$this->fieldMappings[$columnName]]['columnName'];
            } elseif (isset($this->metaMappings[$columnName])) {
                $sql .= $this->metaMappings[$columnName];
            } elseif (isset($this->discriminatorColumns[$columnName])) {
                $sql .= $this->discriminatorColumns[$columnName];
            }

            $sql .= ' AS ' . $columnName;
        }

        return $sql;
    }

    /**
     * @param string $class
     * @param string $alias
     * @param string $dqlAlias
     */
    protected function addAllClassFields($class, $alias, $dqlAlias = null)
    {
        $dqlAlias = $dqlAlias ? : $alias;
        $classMetadata = $this->em->getClassMetadata($class);
        $platform      = $this->em->getConnection()->getDatabasePlatform();

        $this->aliases[$alias] = $alias;

        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {
            if (isset($mapping['inherited'])) {
                continue;
            }

            $columnName = $mapping['columnName'];
            $columnAlias  = $platform->getSQLResultCasing($this->getColumnAlias($columnName));

            if (isset($this->fieldMappings[$columnAlias])) {
                throw new \InvalidArgumentException("The column '$columnName' conflicts with another column in the mapper.");
            }

            $this->addFieldResult($dqlAlias, $columnAlias, $fieldName, $classMetadata->name);
            $this->addColumnAlias($columnAlias, $alias, $columnName);
        }

        foreach ($classMetadata->associationMappings as $associationMapping) {
            if ($associationMapping['isOwningSide'] && $associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                if (isset($associationMapping['inherited'])) {
                    continue;
                }
                foreach ($associationMapping['joinColumns'] as $joinColumn) {
                    $columnName  = $joinColumn['name'];
                    $columnAlias = $platform->getSQLResultCasing($this->getColumnAlias($columnName));

                    if (isset($this->metaMappings[$columnAlias])) {
                        throw new \InvalidArgumentException("The column '$columnAlias' conflicts with another column in the mapper.");
                    }

                    $this->addMetaResult(
                        $dqlAlias,
                        $columnAlias,
                        $columnName,
                        (isset($associationMapping['id']) && $associationMapping['id'] === true)
                    );
                    $this->addColumnAlias($columnAlias, $alias, $columnName);
                }
            }
        }
    }

    /**
     * @param string $columnName
     * @return string
     */
    protected function getColumnAlias($columnName)
    {
        return $columnName . $this->columnAliasCounter++;
    }

    /**
     * @param string $tableName
     * @return string
     */
    protected function getTableAlias($tableName)
    {
        if (!isset($this->tableAliasMap[$tableName])) {
            $this->tableAliasMap[$tableName] = strtolower(preg_replace('~(?<=[a-z]).~i', '', $tableName))
                . $this->tableAliasCounter++;
        }

        return $this->tableAliasMap[$tableName];
    }
}
