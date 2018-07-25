<?php

namespace ITE\DoctrineExtraBundle\ORM\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NativeQuery;

/**
 * Class NativeQueryBuilder
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class NativeQueryBuilder extends QueryBuilder
{
    /**
     * @var EntityManager
     */
    private $_em;

    /**
     * @var ResultSetMappingBuilder $_rsm
     */
    private $_rsm;

    /**
     * @param EntityManager $em
     * @param ResultSetMappingBuilder|null $rsm
     */
    public function __construct(EntityManager $em, ResultSetMappingBuilder $rsm = null)
    {
        $this->_em = $em;
        $this->_rsm = $rsm ? : new ResultSetMappingBuilder($em);
        parent::__construct($this->_em->getConnection());
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->_em;
    }

    /**
     * @param ResultSetMappingBuilder $rsm
     * @return $this
     */
    public function setResultSetMappingBuilder(ResultSetMappingBuilder $rsm)
    {
        $this->_rsm = $rsm;

        return $this;
    }

    /**
     * @return ResultSetMappingBuilder
     */
    public function getResultSetMappingBuilder()
    {
        return $this->_rsm;
    }
    
    /**
     * @return array
     */
    public function getRootAliases()
    {
        $rootAliases = [];

        $from = $this->getQueryPart('from');
        foreach ($from as $fromPart) {
            $rootAliases[] = $fromPart['alias'];
        }

        return $rootAliases;
    }

    /**
     * @return array
     */
    public function getRootEntities()
    {
        $rootAliases = $this->getRootAliases();
        $aliasMap = $this->_rsm->getAliasMap();

        $rootEntities = [];
        foreach ($rootAliases as $rootAlias) {
            if (isset($aliasMap[$rootAlias])) {
                $rootEntities[] = $aliasMap[$rootAlias];
            }
        }

        return $rootEntities;
    }

    /**
     * @return string
     */
    public function getRootAlias()
    {
        $rootAliases = $this->getRootAliases();

        return $rootAliases[0];
    }

    /**
     * @param bool $overrideSelect
     * @return string
     */
    public function getSQL($overrideSelect = true)
    {
        if (self::SELECT === $this->getType()) {
            $aliases = $this->_rsm->getAliases();
            if ($overrideSelect && !empty($aliases)) {
                // modify select
                $select = $this->getQueryPart('select');
                $rx = sprintf('~((%s)\.\*)~', implode('|', $aliases));
                $rsm = $this->_rsm;
                foreach ($select as $i => $selectPart) {
                    $select[$i] = preg_replace_callback($rx, function (array $matches) use ($rsm) {
                        return $rsm->generateEntitySelectClause($matches[2]);
                    }, $selectPart);
                }
                $this->resetQueryPart('select');
                foreach ($select as $selectPart) {
                    $this->addSelect($selectPart);
                }
            }
        }

        return parent::getSQL();
    }

    /**
     * @return NativeQuery
     */
    public function getQuery()
    {
        $sql = $this->getSQL();
        $query = $this->_em->createNativeQuery($sql, $this->_rsm)
            ->setParameters($this->getParameters())
        ;

        return $query;
    }
}
