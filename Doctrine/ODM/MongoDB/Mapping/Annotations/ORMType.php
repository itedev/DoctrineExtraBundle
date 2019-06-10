<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Mapping\Annotations;

use Doctrine\ODM\MongoDB\Mapping\Annotations\AbstractField;

/**
 * @Annotation()
 */
class ORMType extends AbstractField
{
    public $type = 'orm';
}