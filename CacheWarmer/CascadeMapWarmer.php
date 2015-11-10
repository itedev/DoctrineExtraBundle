<?php


namespace ITE\DoctrineExtraBundle\CacheWarmer;

use ITE\DoctrineExtraBundle\CascadeEvent\Map\Loader\CascadeMapLoader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Class CascadeMapWarmer
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeMapWarmer implements CacheWarmerInterface
{
    /**
     * @var CascadeMapLoader
     */
    private $loader;

    /**
     * CascadeMapWarmer constructor.
     *
     * @param CascadeMapLoader $loader
     */
    public function __construct(CascadeMapLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @inheritDoc
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function warmUp($cacheDir)
    {
        $this->loader->loadMap();
    }
}
