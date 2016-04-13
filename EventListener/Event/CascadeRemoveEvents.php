<?php

namespace ITE\DoctrineExtraBundle\EventListener\Event;

/**
 * Class CascadeRemoveEvents
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
final class CascadeRemoveEvents
{
    const PRE_REMOVE = 'ite_doctrine_extra.cascade_remove.pre_remove';
    const POST_REMOVE = 'ite_doctrine_extra.cascade_remove.post_remove';
}
