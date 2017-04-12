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
 * Class DomainEventSubscriber
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class DomainEventSubscriber implements EventSubscriber
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
     * @var bool $enabled
     */
    protected $enabled = true;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return $this
     */
    public function enable()
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function disable()
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        $this->scheduleEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $this->scheduleEntity($event);
    }

    ///**
    // * @param PreUpdateEventArgs $event
    // */
    //public function preUpdate(PreUpdateEventArgs $event)
    //{
    //    $this->scheduleEntity($event);
    //}

    /**
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->scheduleEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
        $this->scheduleEntity($event);
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        foreach ($this->popEntities() as $entity) {
            foreach ($entity->popDomainEvents() as $event) {
                /** @var DomainEvent $event */
                $event->setEntity($entity);
                $this->dispatcher->dispatch($event->getEventName(), $event);
            }
        }
    }

    /**
     * @return array|DomainEventAwareInterface[]
     */
    protected function popEntities()
    {
        $entities = $this->entities;
        $this->entities = [];

        return $entities;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    protected function scheduleEntity(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if (!$entity instanceof DomainEventAwareInterface) {
            return;
        }

        $hash = spl_object_hash($entity);
        $this->entities[$hash] = $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            'postLoad',
            'postPersist',
            //'preUpdate',
            'postUpdate',
            'postRemove',
            'postFlush',
        ];
    }
}
