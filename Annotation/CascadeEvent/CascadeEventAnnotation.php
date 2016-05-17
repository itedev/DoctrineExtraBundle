<?php


namespace ITE\DoctrineExtraBundle\Annotation\CascadeEvent;

/**
 * Class CascadeEventAnnotation
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 * @Annotation
 */
class CascadeEventAnnotation
{
    /**
     * @var bool
     */
    public $reverse = false;

    /**
     * @var string
     */
    public $propertyPath;
    
    /**
     * @var null|string
     */
    public $method = null;
}
