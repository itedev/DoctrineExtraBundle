<?php


namespace ITE\DoctrineExtraBundle\CascadeEvent\Map;

use ITE\DoctrineExtraBundle\CascadeEvent\Map\Loader\CascadeMapLoader;

/**
 * Class CascadeMap
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeMap
{
    const EVENT_TYPE_PERSIST = 'persist';
    const EVENT_TYPE_UPDATE = 'update';
    const EVENT_TYPE_REMOVE = 'remove';

    /**
     * @var array
     */
    private $map;

    /**
     * @var CascadeMapLoader
     */
    private $loader;

    /**
     * CascadeMap constructor.
     *
     * @param CascadeMapLoader $loader
     */
    public function __construct(CascadeMapLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @param string      $className
     * @param string|null $eventType
     *
     * @return array
     */
    public function getMetadataForClass($className, $eventType = null)
    {
        if (isset($this->map[$className])) {
            if (null === $eventType) {
                return $this->map[$className];
            }
        }
        $this->map[$className] = $this->loader->loadMapForClass($className);

        return $eventType ? $this->map[$className][$eventType] : $this->map[$className];
    }
}
