<?php

namespace ITE\DoctrineExtraBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\DomainEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class CascadeUpdateSubscriber
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class CascadeUpdateSubscriber implements EventSubscriber
{
    /**
     * @var EventDispatcherInterface $dispatcher
     */
    protected $dispatcher;

    /**
     * @var array|DomainEventAwareInterface[] $entities
     */
    protected $entities = [];

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function preUpdate(PreUpdateEventArgs $event)
    {

    }

    public function postFlush(PostFlushEventArgs $event)
    {

    }

    /**
     * @param LifecycleEventArgs $event
     */
    protected function storeEntity(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if (!$entity instanceof DomainEventAwareInterface || in_array($entity, $this->entities, true)) {
            return;
        }

        $this->entities[] = $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            'preUpdate',
            'postFlush',
        ];
    }
}
