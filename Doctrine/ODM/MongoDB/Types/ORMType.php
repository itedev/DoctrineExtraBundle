<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Types;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use ORG\AppBundle\Util\Traits\PropertyAccessorAwareTrait;

/**
 * Class ORMType
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ORMType extends MappingAwareType
{
    use PropertyAccessorAwareTrait;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @param EntityManager $em
     *
     * @return ORMType
     */
    public function setEm(EntityManager $em)
    {
        $this->em = $em;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValueWithMapping($value, array $mapping)
    {
        if (null === $value) {
            return null;
        }

        $class = $mapping['options']['class'] ?? null;

        if (!is_object($value)) {
            throw new \UnexpectedValueException(sprintf('Expected Entity Object, but "%s" given', gettype($value)));
        }

        if ($class && !($value instanceof $class)) {
            throw new \UnexpectedValueException(sprintf('Expected class "%s", "%s" given', $class, get_class($value)));
        }

        $additionalFields = $mapping['options']['additionalFields'] ?? [];

        $data = [
            'class' => ClassUtils::getClass($value),
            'id' => $value->getId(),
        ];

        foreach ($additionalFields as $additionalField) {
            $data[$additionalField] = $this->getPropertyAccessor()->getValue($value, $additionalField);
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function closureToPHPWithMapping($value, array $mapping)
    {
        $class = $mapping['options']['class'] ?? null;

        if (!is_array($value)) {
            return null;
        }

        if ($class) {
            $actualClass = new \ReflectionClass($value['class']);

            if ($value['class'] !== $class && !$actualClass->isSubclassOf($class)) {
                throw new \UnexpectedValueException(
                    sprintf('Expected class "%s", "%s" given', $class, $value['class'])
                );
            }
        }

        $proxy = $mapping['options']['proxy'] ?? true;

        return $proxy ? $this->em->getReference($class, $value['id']) : $this->em->getRepository($class)->find($value['id']);
    }
}
