<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event;

/**
 * Class CascadeRemoveEvents
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
final class CascadeRemoveEvents
{
    const PRE_ONE_TO_MANY_REMOVE = 'ite_doctrine_extra.cascade_remove.pre_one_to_many_remove';
    const POST_ONE_TO_MANY_REMOVE = 'ite_doctrine_extra.cascade_remove.post_one_to_many_remove';
    const PRE_ONE_TO_ONE_REMOVE = 'ite_doctrine_extra.cascade_remove.pre_one_to_one_remove';
    const POST_ONE_TO_ONE_REMOVE = 'ite_doctrine_extra.cascade_remove.post_one_to_one_remove';
}
