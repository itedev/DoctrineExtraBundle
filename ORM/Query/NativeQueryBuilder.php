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
     * @param ResultSetMappingBuilder $rsm
     */
    public function __construct(EntityManager $em, ResultSetMappingBuilder $rsm)
    {
        $this->_em = $em;
        $this->_rsm = $rsm;
        parent::__construct($this->_em->getConnection());
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
     * @return mixed
     */
    public function getRootAlias()
    {
        $rootAliases = $this->getRootAliases();

        return $rootAliases[0];
    }

    /**
     * @return NativeQuery
     */
    public function getQuery()
    {
        $aliases = $this->_rsm->getAliases();
        if (!empty($aliases)) {
            // modify select
            $select = $this->getQueryPart('select');
            $rx = sprintf('~((%s)\.\*)~', implode('|', $aliases));
            $rsm = $this->_rsm;
            foreach ($select as $i => $selectPart) {
                $select[$i] = preg_replace_callback($rx, function(array $matches) use ($rsm) {
                    return $rsm->generateEntitySelectClause($matches[2]);
                }, $selectPart);
            }
            $this->resetQueryPart('select');
            foreach ($select as $selectPart) {
                $this->addSelect($selectPart);
            }
        }

        $sql = $this->getSQL();
        $query = $this->_em->createNativeQuery($sql, $this->_rsm)
            ->setParameters($this->getParameters())
        ;

        return $query;
    }
}