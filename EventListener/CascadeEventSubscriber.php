<?php


namespace ITE\DoctrineExtraBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use ITE\DoctrineExtraBundle\CascadeEvent\CascadeEvents;
use ITE\DoctrineExtraBundle\CascadeEvent\Map\CascadeMap;
use ITE\DoctrineExtraBundle\CascadeEvent\PropertyPath\Resolver;
use ITE\DoctrineExtraBundle\EventListener\Event\CascadeEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class CascadeEventSubscriber
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeEventSubscriber implements EventSubscriber
{
    /**
     * @var LifecycleEventArgs[]
     */
    protected $persisted = [];

    /**
     * @var LifecycleEventArgs[]
     */
    protected $updated = [];

    /**
     * @var LifecycleEventArgs[]
     */
    protected $removed = [];
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * CascadeEventSubscriber constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $this->persisted[] = $event;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->updated[] = $event;
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
        $this->removed[] = $event;
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        $persisted = $this->persisted;
        $this->persisted = [];
        $this->processEventArray($persisted, CascadeMap::EVENT_TYPE_PERSIST);

        $updated = $this->updated;
        $this->updated = [];
        $this->processEventArray($updated, CascadeMap::EVENT_TYPE_UPDATE);

        $removed = $this->removed;
        $this->removed = [];
        $this->processEventArray($removed, CascadeMap::EVENT_TYPE_REMOVE);
    }

    protected function processEventArray($array, $eventName)
    {
        $propertyPathResolver = $this->container->get('ite_doctrine_extra.cascade_event.property_path.resolver');
        $cascadeMap           = $this->container->get('ite_doctrine_extra.cascade_event.map');
        $dispatcher           = $this->container->get('event_dispatcher');
        /** @var LifecycleEventArgs $item */
        foreach ($array as $item) {
            $className = get_class($item->getEntity());
            $map = $cascadeMap->getMetadataForClass($className, $eventName);

            if (!empty($map)) {
                foreach ($map as $value) {
                    if ($value['origin_class'] === $className) {
                        $targetEntities = $propertyPathResolver->resolve(
                            $item->getEntity(),
                            $value['target_class'],
                            $value['property_path'],
                            $value['reverse']
                        );

                        foreach ($targetEntities as $targetEntity) {
                            $cascadeEventName = CascadeEvents::BASE_NAME.'.'.$eventName;
                            $event = new CascadeEvent(
                                $cascadeEventName,
                                $item->getEntity(),
                                $targetEntity
                            );
                            $dispatcher->dispatch($cascadeEventName, $event);
                            if (null !== $value['method']) {
                                call_user_func([$targetEntity, $value['method']], $event);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'postUpdate',
            'postRemove',
            'postFlush',
        ];
    }
}
