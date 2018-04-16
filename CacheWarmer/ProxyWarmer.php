<?php

namespace ITE\DoctrineExtraBundle\CacheWarmer;

use ITE\DoctrineExtraBundle\Proxy\ProxyFactory;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Class ProxyWarmer
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ProxyWarmer implements CacheWarmerInterface
{
    /**
     * @var ProxyFactory $proxyFactory
     */
    private $proxyFactory;

    /**
     * @var string $proxyDir
     */
    private $proxyDir;

    /**
     * ProxyWarmer constructor.
     *
     * @param ProxyFactory $proxyFactory
     * @param string $proxyDir
     */
    public function __construct(ProxyFactory $proxyFactory, $proxyDir)
    {
        $this->proxyFactory = $proxyFactory;
        $this->proxyDir = $proxyDir;
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
        $reflection = new \ReflectionClass('Doctrine\ORM\EntityManager');
        $instance = $reflection->newInstanceWithoutConstructor();
        $proxy = $this->proxyFactory->createProxy($instance);
        $proxyReflection = new \ReflectionObject($proxy);
        $filename = $proxyReflection->getFileName();
        $proxyCode = file_get_contents($filename);
        $newPath = $cacheDir.'/'.$this->proxyDir.'/'.basename($filename);
        file_put_contents($newPath, $proxyCode);
    }
}