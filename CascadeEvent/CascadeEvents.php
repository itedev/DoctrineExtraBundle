<?php


namespace ITE\DoctrineExtraBundle\CascadeEvent;

/**
 * Class CascadeEvents
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeEvents
{
    const BASE_NAME  = 'ite_doctrine_extra.cascade_event';
    const PERSIST    = 'ite_doctrine_extra.cascade_event.persist';
    const UPDATE     = 'ite_doctrine_extra.cascade_event.update';
    const REMOVE     = 'ite_doctrine_extra.cascade_event.remove';

    private function __construct()
    {
    }
}
