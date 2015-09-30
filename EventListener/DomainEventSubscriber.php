<?php

namespace ITE\DoctrineExtraBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use ITE\DoctrineExtraBundle\DomainEvent\DomainEventAwareInterface;
use ITE\DoctrineExtraBundle\EventListener\Event\DomainEvent;
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
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        $this->storeEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $this->storeEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->storeEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
        $this->storeEntity($event);
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        foreach ($this->popEntities() as $entity) {
            foreach ($entity->popEvents() as $event) {
                /** @var DomainEvent $event */
                $event->setSubject($entity);
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
            'postLoad',
            'postPersist',
            'postUpdate',
            'postRemove',
            'postFlush',
        ];
    }
}
