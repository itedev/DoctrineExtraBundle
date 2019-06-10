<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\EventListener;

use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;

/**
 * Class ORMAwareEventListener
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ORMTypeEventListener
{
    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function onPostLoad(LifecycleEventArgs $eventArgs)
    {
        $document = $eventArgs->getDocument();
    }
}
