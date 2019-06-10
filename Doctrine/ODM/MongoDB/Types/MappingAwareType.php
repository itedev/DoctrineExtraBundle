<?php


namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Types;

use Doctrine\ODM\MongoDB\Types\Type;

/**
 * Class MappingAwareType
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
abstract class MappingAwareType extends Type
{
    public function closureToMongo()
    {
        return '$return = \Doctrine\ODM\MongoDB\Types\Type::getType($typeIdentifier)->convertToDatabaseValueWithMapping($value, $mapping);';
    }

    public function closureToPHP()
    {
        return '$return = \Doctrine\ODM\MongoDB\Types\Type::getType($typeIdentifier)->closureToPHPWithMapping($value, $mapping);';
    }

    /**
     * @param mixed $value
     * @param array $mapping
     *
     * @return mixed
     */
    abstract public function convertToDatabaseValueWithMapping($value, array $mapping);

    /**
     * @param mixed $value
     * @param array $mapping
     *
     * @return mixed
     */
    abstract public function closureToPHPWithMapping($value, array $mapping);
}