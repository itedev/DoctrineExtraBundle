<?php

namespace ITE\DoctrineExtraBundle\Dependency\Counter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class EntityCounter
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class EntityCounter implements EntityCounterInterface
{
    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * @var array $associationNames
     */
    protected $associationNames;

    /**
     * @var ClassMetadata $targetClassMetadata
     */
    protected $targetClassMetadata;

    /**
     * @var ClassMetadata $classMetadata
     */
    protected $classMetadata;

    /**
     * @var array $cache
     */
    protected $cache = [];

    /**
     * @param EntityManager $em
     * @param ClassMetadata $targetClassMetadata
     * @param ClassMetadata $classMetadata
     * @param array $associationNames
     */
    public function __construct(
        EntityManager $em,
        ClassMetadata $targetClassMetadata,
        ClassMetadata $classMetadata,
        array $associationNames
    ) {

        $this->em = $em;
        $this->targetClassMetadata = $targetClassMetadata;
        $this->classMetadata = $classMetadata;
        $this->associationNames = $associationNames;
    }

    /**
     * {@inheritdoc}
     */
    public function count($entity, $force = false)
    {
        $identifier = $this->targetClassMetadata->getIdentifierValues($entity);
        $cacheKey = serialize($identifier);
        if (array_key_exists($cacheKey, $this->cache) && !$force) {
            return $this->cache[$cacheKey];
        }

        $alias = 'o';
        $qb = $this->em->createQueryBuilder()
            ->select(sprintf('COUNT(%s)', $alias))
            ->from($this->classMetadata->getName(), $alias)
        ;
        $orX = $qb->expr()->orX();
        foreach ($this->associationNames as $associationName) {
            $orX->add($qb->expr()->eq(sprintf('%s.%s', $alias, $associationName), ':' . $associationName));
            $qb->setParameter(':' . $associationName, reset($identifier));
        }

        $count = (int) $qb
            ->where($orX)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->cache[$cacheKey] = $count;

        return $count;
    }
}
