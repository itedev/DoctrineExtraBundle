<?php

namespace ITE\DoctrineExtraBundle\ORM;

use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder as BaseQueryBuilder;
use ITE\Common\Util\ReflectionUtils;

/**
 * Class QueryBuilder
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class QueryBuilder extends BaseQueryBuilder
{
    /**
     * @return ResultSetMapping
     */
    public function getResultSetMappingBuilder()
    {
        $query = $this->getQuery();
        $query->getSQL();
        /** @var ParserResult $parserResult */
        $parserResult = ReflectionUtils::getValue($query, '_parserResult');

        return $parserResult->getResultSetMapping();
    }
}
