<?php

namespace ITE\DoctrineExtraBundle\Entity;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository as BaseEntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use ITE\DoctrineExtraBundle\ORM\Query\NativeQueryBuilder;
use ITE\DoctrineExtraBundle\ORM\Query\ResultSetMappingBuilder;
use ITE\DoctrineExtraBundle\ORM\QueryBuilder as ExtendedQueryBuilder;

/**
 * Class EntityRepository
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class EntityRepository extends BaseEntityRepository
{
    /**
     * @var string $_alias
     */
    protected $_alias;

    /**
     * @var int
     */
    private $sqlCounter = 0;

    /**
     * @param QueryBuilder $qb
     * @return int
     */
    public function countQueryBuilder(QueryBuilder $qb)
    {
        $countQb = clone $qb;
        $rootAliases = $qb->getRootAliases();

        return (int) $countQb
            ->select($countQb->expr()->count($rootAliases[0]))
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count by criteria.
     *
     * @param array $criteria
     * @return int
     */
    public function countBy($criteria = [])
    {
        $alias = $this->getAlias();
        $qb = $this->createQueryBuilder($alias);

        foreach ($criteria as $key => $item) {
            $qb->andWhere(sprintf('%s.%s %s :%s', $alias, $key, is_array($item) ? 'IN' : '=', $key));
            $qb->setParameter($key, $item);
        }

        return $this->countQueryBuilder($qb);
    }

    /**
     * @param QueryBuilder $qb
     * @param $limit
     * @param int $offset
     * @return array
     */
    public function getSlicedResult(QueryBuilder $qb, $limit, $offset = 0)
    {
        $orderBy = $qb->getDQLPart('orderBy');
        if (empty($orderBy)) {
            $rootAliases = $qb->getRootAliases();
            $identifierFieldNames = $this->_class->getIdentifierFieldNames();
            foreach ($identifierFieldNames as $fieldName) {
                $qb->addOrderBy($rootAliases[0].'.'.$fieldName);
            }
        }

        return $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $alias
     * @return ResultSetMappingBuilder
     */
    public function createNativeResultSetMappingBuilder($alias = null)
    {
        $alias = null !== $alias ? $alias : $this->getAlias();

        $rsm = new ResultSetMappingBuilder($this->_em);
        $rsm->addRootEntityFromClassMetadata($this->_entityName, $alias);

        return $rsm;
    }

    /**
     * @param ResultSetMappingBuilder $rsm
     * @param string $alias
     * @return NativeQueryBuilder
     */
    public function createNativeQueryBuilder(ResultSetMappingBuilder $rsm, $alias = null)
    {
        $alias = null !== $alias ? $alias : $this->getAlias();

        $qb = new NativeQueryBuilder($this->_em, $rsm);

        return $qb
            ->select($alias . '.*')
            ->from($this->_class->getTableName(), $alias)
            ;
    }

    /**
     * @param string|null $alias
     *
     * @return ExtendedQueryBuilder
     */
    public function createQueryBuilder($alias = null)
    {
        $alias = null !== $alias ? $alias : $this->getAlias();

        $qb = new ExtendedQueryBuilder($this->_em);
        $qb->select($alias)
            ->from($this->_entityName, $alias);

        return $qb;
    }

    /**
     * @return QueryBuilder
     */
    public function createUpdateQueryBuilder()
    {
        return $this->_em
            ->createQueryBuilder()
            ->update($this->_entityName, $this->getAlias());
    }

    /**
     * @return QueryBuilder
     */
    public function createCountQueryBuilder()
    {
        return $this->createQueryBuilder()
            ->select(sprintf('COUNT(%s)', $this->getAlias()));
    }

    /**
     * @return int
     */
    public function count()
    {
        return (int) $this->createCountQueryBuilder()
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string $indexedFieldName
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getIndexedByFieldNameQueryBuilder($indexedFieldName, array $orderBy = null)
    {
        $qb = $this->_em->createQueryBuilder()
            ->select($this->getAlias())
            ->from($this->_entityName, $this->getAlias(), sprintf('%s.%s', $this->getAlias(), $indexedFieldName));
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param string $indexedFieldName
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFieldValuesIndexedByFieldNameQueryBuilder($fieldName, $indexedFieldName, array $orderBy = null)
    {
        $qb = $this->_em->createQueryBuilder()
            ->select(sprintf('%s.%s', $this->getAlias(), $fieldName))
            ->from($this->_entityName, $this->getAlias(), sprintf('%s.%s', $this->getAlias(), $indexedFieldName));
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldValue
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFilteredByFieldValueQueryBuilder($fieldName, $fieldValue, array $orderBy = null)
    {
        $qb = $this->createQueryBuilder();
        $this->filterByFieldValue($qb, $fieldName, $fieldValue);
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFilteredByFieldValuesQueryBuilder($fieldName, array $fieldValues, array $orderBy = null)
    {
        $qb = $this->createQueryBuilder();
        $this->filterByFieldValues($qb, $fieldName, $fieldValues);
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getIndexedAndFilteredByFieldValuesQueryBuilder($fieldName, array $fieldValues,
        array $orderBy = null)
    {
        $qb = $this->getIndexedByFieldNameQueryBuilder($fieldName);
        $this->filterByFieldValues($qb, $fieldName, $fieldValues);
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getExistingFieldValuesByFieldValuesQueryBuilder($fieldName, array $fieldValues,
        array $orderBy = null)
    {
        $qb = $this->getFilteredByFieldValuesQueryBuilder($fieldName, $fieldValues);
        $qb->select(sprintf('%s.%s', $this->getAlias(), $fieldName));
        $this->internalOrderBy($qb, $orderBy);

        return $qb;
    }

    /**
     * @param string $fieldName
     * @param array $orderBy
     * @return array
     */
    protected function findAllIndexedByFieldName($fieldName, array $orderBy = null)
    {
        return $this
            ->getIndexedByFieldNameQueryBuilder($fieldName, $orderBy)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $fieldName
     * @param string $indexedFieldName
     * @param array $orderBy
     * @return array
     */
    protected function findFieldValuesIndexedByFieldName($fieldName, $indexedFieldName, array $orderBy = null)
    {
        $result = $this
            ->getFieldValuesIndexedByFieldNameQueryBuilder($fieldName, $indexedFieldName, $orderBy)
            ->getQuery()
            ->getArrayResult();

        return array_map(function($item) use ($fieldName) {
            return $item[$fieldName];
        }, $result);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldValue
     * @param array $orderBy
     * @return array
     */
    protected function findFilteredByFieldValue($fieldName, $fieldValue, array $orderBy = null)
    {
        return $this
            ->getFilteredByFieldValueQueryBuilder($fieldName, $fieldValue, $orderBy)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return array
     */
    protected function findFilteredByFieldValues($fieldName, array $fieldValues, array $orderBy = null)
    {
        return $this
            ->getFilteredByFieldValuesQueryBuilder($fieldName, $fieldValues, $orderBy)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return array
     */
    protected function findIndexedAndFilteredByFieldValues($fieldName, array $fieldValues, array $orderBy = null)
    {
        if (empty($fieldValues)) {
            return [];
        }
        $qb = $this->getIndexedAndFilteredByFieldValuesQueryBuilder($fieldName, $fieldValues, $orderBy);

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $fieldName
     * @param array $fieldValues
     * @param array $orderBy
     * @return array
     */
    protected function findExistingFieldValuesByFieldValues($fieldName, array $fieldValues, array $orderBy = null)
    {
        if (empty($fieldValues)) {
            return [];
        }
        $result = $this
            ->getExistingFieldValuesByFieldValuesQueryBuilder($fieldName, $fieldValues, $orderBy)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(function($row) use ($fieldName) {
            return $row[$fieldName];
        }, $result);
    }

    /**
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getIndexedByIdQueryBuilder(array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->getIndexedByFieldNameQueryBuilder($identifierFieldName, $orderBy);
    }

    /**
     * @param string $fieldName
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFieldValuesIndexedByIdQueryBuilder($fieldName, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->getFieldValuesIndexedByFieldNameQueryBuilder($fieldName, $identifierFieldName, $orderBy);
    }

    /**
     * @param mixed $id
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFilteredByIdQueryBuilder($id, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->getFilteredByFieldValueQueryBuilder($identifierFieldName, $id, $orderBy);
    }

    /**
     * @param array $ids
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getFilteredByIdsQueryBuilder(array $ids, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->getFilteredByFieldValuesQueryBuilder($identifierFieldName, $ids, $orderBy);
    }

    /**
     * @param array $ids
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function getIndexedAndFilteredByIdsQueryBuilder(array $ids, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->getIndexedAndFilteredByFieldValuesQueryBuilder($identifierFieldName, $ids, $orderBy);
    }

    /**
     * @param array $orderBy
     * @return array
     */
    protected function findAllIndexedById(array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->findAllIndexedByFieldName($identifierFieldName, $orderBy);
    }

    /**
     * @param string $fieldName
     * @param array $orderBy
     * @return array
     */
    protected function findFieldValuesIndexedById($fieldName, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->findFieldValuesIndexedByFieldName($fieldName, $identifierFieldName, $orderBy);
    }

    /**
     * @param array $ids
     * @param array $orderBy
     * @return array
     */
    protected function findFilteredByIds(array $ids, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->findFilteredByFieldValues($identifierFieldName, $ids, $orderBy);
    }

    /**
     * @param array $ids
     * @param array $orderBy
     * @return array
     */
    protected function findIndexedAndFilteredByIds(array $ids, array $orderBy = null)
    {
        $identifierFieldName = $this->getIdentifierFieldName();

        return $this->findIndexedAndFilteredByFieldValues($identifierFieldName, $ids, $orderBy);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $fieldName
     * @param mixed $fieldValue
     * @param string $operator
     * @return QueryBuilder
     */
    protected function filterByFieldValue(QueryBuilder $qb, $fieldName, $fieldValue, $operator = '=')
    {
        $parameterName = $this->getParameterName($fieldName);

        if (!is_null($fieldValue)) {
            $qb
                ->andWhere(sprintf('%s.%s %s :%s', $this->getAlias(), $fieldName, $operator, $parameterName))
                ->setParameter($parameterName, $fieldValue);
        } else {
            if ('=' === $operator) {
                $qb->andWhere(sprintf('%s.%s IS NULL', $this->getAlias(), $fieldName));
            } elseif ('!=' === $operator) {
                $qb->andWhere(sprintf('%s.%s IS NOT NULL', $this->getAlias(), $fieldName));
            }
        }

        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @param string $fieldName
     * @param array $fieldValues
     * @param bool $in
     * @return QueryBuilder
     */
    protected function filterByFieldValues(QueryBuilder $qb, $fieldName, array $fieldValues, $in = true)
    {
        $parameterName = $this->getParameterName(Inflector::pluralize($fieldName));
        $operator = $in ? 'IN' : 'NOT IN';

        return $qb
            ->andWhere(sprintf('%s.%s %s (:%s)', $this->getAlias(), $fieldName, $operator, $parameterName))
            ->setParameter($parameterName, $fieldValues);
    }

    /**
     * @param QueryBuilder $qb
     * @param mixed $id
     * @return QueryBuilder
     */
    protected function filterById(QueryBuilder $qb, $id)
    {
        $identifierFieldName = $this->getIdentifierFieldName();
        $parameterName = $this->getParameterName('id');

        return $qb
            ->andWhere(sprintf('%s.%s = :%s', $this->getAlias(), $identifierFieldName, $parameterName))
            ->setParameter($parameterName, $id);
    }

    /**
     * @param QueryBuilder $qb
     * @param array $ids
     * @return QueryBuilder
     */
    protected function filterByIds(QueryBuilder $qb, array $ids)
    {
        $identifierFieldName = $this->getIdentifierFieldName();
        $parameterName = $this->getParameterName('ids');

        return $qb
            ->andWhere(sprintf('%s.%s IN (:%s)', $this->getAlias(), $identifierFieldName, $parameterName))
            ->setParameter($parameterName, $ids);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $fieldName
     * @param mixed $order
     * @return QueryBuilder
     */
    protected function orderBy(QueryBuilder $qb, $fieldName, $order = null)
    {
        if ($this->_class->hasField($fieldName)) {
            $fieldName = sprintf('%s.%s', $this->getAlias(), $fieldName);
        }

        return $qb->orderBy($fieldName, $order);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $fieldName
     * @param mixed $order
     * @return QueryBuilder
     */
    protected function addOrderBy(QueryBuilder $qb, $fieldName, $order = null)
    {
        if ($this->_class->hasField($fieldName)) {
            $fieldName = sprintf('%s.%s', $this->getAlias(), $fieldName);
        }

        return $qb->addOrderBy($fieldName, $order);
    }

    /**
     * @return string
     */
    protected function getAlias()
    {
        if (!isset($this->_alias)) {
            $this->_alias = strtolower(
                preg_replace('~[a-z]~', '', substr($this->_entityName, strrpos($this->_entityName, '\\') + 1))
            );
        }

        return $this->_alias;
    }


    /**
     * @param string $parameterName
     * @return string
     */
    protected function getParameterName($parameterName)
    {
        return $parameterName . $this->sqlCounter++;
    }

    /**
     * @param QueryBuilder $qb
     * @param string $fieldName
     * @param mixed $fieldValue
     * @return QueryBuilder
     */
    protected function setFieldValue(QueryBuilder $qb, $fieldName, $fieldValue)
    {
        $parameterName = $this->getParameterName($fieldName);

        return $qb
            ->set(sprintf('%s.%s', $this->getAlias(), $fieldName), sprintf(':%s', $parameterName))
            ->setParameter($parameterName, $fieldValue);
    }

    /**
     * @return string
     */
    protected function getIdentifierFieldName()
    {
        return $this->_class->getSingleIdentifierFieldName();
    }

    /**
     * @param QueryBuilder $qb
     * @param array $orderBy
     * @return QueryBuilder
     */
    protected function internalOrderBy(QueryBuilder $qb, array $orderBy = null)
    {
        if (isset($orderBy) && !empty($orderBy) && isset($orderBy[0])) {
            $fieldName = $orderBy[0];
            $order = isset($orderBy[1]) ? $orderBy[1] : null;

            return $this->addOrderBy($qb, $fieldName, $order);
        }

        return $qb;
    }
}