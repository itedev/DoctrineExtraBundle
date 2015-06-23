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
     * @var array $aliases
     */
    protected $aliases = [];

    /**
     * @var int $sqlCounter
     */
    private $sqlCounter = 0;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param string $class
     * @param string $alias
     */
    public function addRootEntityFromClassMetadata($class, $alias)
    {
        $this->addEntityResult($class, $alias);
        $this->addAllClassFields($class, $alias);

        $this->aliases[$alias] = $alias;
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

        $this->aliases[$alias] = $alias;
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

        $discrColumn = $classMetadata->discriminatorColumn['name'];
        $resultColumnName = $platform->getSQLResultCasing($discrColumn);

        $this->setDiscriminatorColumn($parentAlias, $resultColumnName);
        $this->addMetaResult($parentAlias, $resultColumnName, $discrColumn);
        $this->columnTableMap[$resultColumnName] = $alias;

        $this->addAllClassFields($class, $alias, $parentAlias);

        $this->aliases[$alias] = $alias;
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
            } else if (isset($this->metaMappings[$columnName])) {
                $sql .= $this->metaMappings[$columnName];
            } else if (isset($this->discriminatorColumns[$columnName])) {
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
            $this->columnTableMap[$columnAlias] = $alias;
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
                    $this->columnTableMap[$columnAlias] = $alias;
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
        return $columnName . $this->sqlCounter++;
    }
}