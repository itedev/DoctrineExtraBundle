<?php

namespace ITE\DoctrineExtraBundle\CacheWarmer;

use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Mapping\ClassMetadataMappingLoader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Class DoctrineMongoDBMappingCacheWarmer
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class DoctrineMongoDBMappingCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var ClassMetadataMappingLoader
     */
    private $loader;

    /**
     * DoctrineMongoDBMappingCacheWarmer constructor.
     *
     * @param ClassMetadataMappingLoader $loader
     */
    public function __construct(ClassMetadataMappingLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * {@inheritDoc}
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function warmUp($cacheDir)
    {
        $this->loader->loadAllMappings();
    }
}