<?php

namespace ITE\DoctrineExtraBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent\BatchDomainEvent;
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
            /** @var BatchDomainEvent|null $batchEvent */
            $batchEvent = null;
            foreach ($entity->popDomainEvents() as $event) {
                /** @var DomainEvent $event */
                $event->setEntity($entity);

                if ($event->isGrouped()) {
                    if (null === $batchEvent) {
                        $batchEvent = new BatchDomainEvent($event->getEventName());
                    }
                    if ($event->getEventName() === $batchEvent->getEventName()) {
                        $batchEvent->addEvent($event);
                    } else {
                        $this->dispatcher->dispatch($batchEvent->getEventName(), $batchEvent);
                        $batchEvent = new BatchDomainEvent($event->getEventName());
                        $batchEvent->addEvent($event);
                    }
                } else {
                    if (null !== $batchEvent) {
                        $this->dispatcher->dispatch($batchEvent->getEventName(), $batchEvent);
                        $batchEvent = null;
                    }
                    $this->dispatcher->dispatch($event->getEventName(), $event);
                }
            }
            if (null !== $batchEvent) {
                $this->dispatcher->dispatch($batchEvent->getEventName(), $batchEvent);
                $batchEvent = null;
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
