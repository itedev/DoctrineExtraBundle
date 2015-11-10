<?php


namespace ITE\DoctrineExtraBundle\CascadeEvent\PropertyPath;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Class Resolver
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class Resolver
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * Resolver constructor.
     *
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Resolve the child entity by the given class name and property path
     *
     * @param object $entity
     * @param string $className
     * @param string $propertyPath
     *
     * @return array
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function resolve($entity, $className, $propertyPath)
    {
        $queryNeeded = get_class($entity) === $className ? false : true;
        $accessor = PropertyAccess::createPropertyAccessor();
        $pp = new PropertyPath($propertyPath);
        $parts = $pp->getElements();

        if (count($parts) == 0) {
            throw new \RuntimeException(sprintf('The given property path "" is invalid or empty.', $propertyPath));
        }

        $metadata = $this->em->getClassMetadata($className);
        $repository = $this->em->getRepository($className);
        $qb = $repository->createQueryBuilder('t0');
        $currentEntity = $entity;
        $key = 0;

        foreach ($parts as $key => $part) {
            $association = $metadata->getAssociationMapping($part);

            // we have no multiple fields, so, additional loading is not needed
            if (!$queryNeeded && ($association['type'] === ClassMetadataInfo::ONE_TO_ONE
             || $association['type'] === ClassMetadataInfo::MANY_TO_ONE)
            ) {
                $currentEntity = $accessor->getValue($currentEntity, $part);
            } else {
                $queryNeeded = true;
            }

            // build the query builder too for the case we have one-to-many or many-to-many relations
            $qb->innerJoin('t'.$key.'.'.$part, 't'.($key + 1));
        }

        if (!$queryNeeded) {
            return [$currentEntity];
        }

        $qb
            ->where('t'.($key + 1).'=:entity')
            ->setParameter('entity', $entity);

        return $qb->getQuery()->getResult();
    }
}
