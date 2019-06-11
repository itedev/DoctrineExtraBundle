<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Mapping\ClassMetadataMappingLoader;
use ORG\AppBundle\Util\Traits\PropertyAccessorAwareTrait;

/**
 * Class ORMTypeEventSubscriber
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ORMTypeEventSubscriber implements EventSubscriber
{
    use PropertyAccessorAwareTrait;

    /**
     * @var ClassMetadataMappingLoader
     */
    private $loader;

    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var Builder[]
     */
    private $scheduled = [];

    /**
     * ORMTypeEventSubscriber constructor.
     *
     * @param ClassMetadataMappingLoader $loader
     */
    public function __construct(ClassMetadataMappingLoader $loader, DocumentManager $dm)
    {
        $this->loader = $loader;
        $this->dm     = $dm;
    }

    /**
     * @param PreUpdateEventArgs $eventArgs
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        $mappings = $this->getMappingsForClass(get_class($entity));

        if (empty($mappings)) {
            return;
        }

        foreach ($mappings as $documentClassName => $mapping) {
            foreach ($mapping as $field => $options) {
                $additionalFields = $options['additionalFields'] ?? [];

                if (empty($additionalFields)) {
                    continue;
                }

                $changed = false;

                foreach ($additionalFields as $field) {
                    if ($eventArgs->hasChangedField($field)) {
                        $changed = true;
                    }
                }

                if (!$changed) {
                    continue;
                }

                $qb = $this->dm->createQueryBuilder($documentClassName);
                $qb
                    ->findAndUpdate()
                    ->field($field.'.id')->equals($entity->getId())
                ;

                foreach ($additionalFields as $additionalField) {
                    $qb->field($field.'.'.$additionalField)->set($this->getPropertyAccessor()->getValue($entity, $additionalField));
                }

                $this->scheduled[] = $qb;
            }
        }
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        $mappings = $this->getMappingsForClass(get_class($entity));

        if (empty($mappings)) {
            return;
        }

        foreach ($mappings as $documentClassName => $mapping) {
            foreach ($mapping as $field => $options) {
                if ('RESTRICT' === $options['onDelete']) {
                    throw new \RuntimeException(sprintf(
                        'Option "onDelete" value is set to "RESTRICT" for ORM field "%s" in class "%s". Removing is denied.',
                        $field,
                        $documentClassName
                    ));
                } else {
                    $qb = $this->dm->createQueryBuilder($documentClassName);
                    $qb
                        ->field($field.'.id')->equals($entity->getId())
                    ;

                    if ('CASCADE' === $options['onDelete']) {
                        $qb->findAndRemove();
                    } else {
                        $qb
                            ->findAndUpdate()
                            ->field($field)->set(null)
                        ;
                    }

                    $this->scheduled[] = $qb;
                }
            }
        }
    }

    public function postFlush()
    {
        foreach ($this->scheduled as $item) {
            $item->getQuery()->execute();
        }

        $this->scheduled = [];
    }

    public function onClear()
    {
        $this->scheduled = [];
    }

    /**
     * @param string $className
     *
     * @return array
     */
    private function getMappingsForClass(string $className)
    {
        $mappings = $this->loader->loadORMTypeMappingsForClass($className);

        if (empty($mappings) && false !== $parentClass = get_parent_class($className)) {
            return $this->getMappingsForClass($parentClass);
        }

        return $mappings;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::preUpdate,
            Events::preRemove,
            Events::postFlush,
            Events::onClear
        ];
    }
}
