<?php

namespace ITE\DoctrineExtraBundle;

use ITE\DoctrineExtraBundle\DependencyInjection\Compiler\ProxyPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class ITEDoctrineExtraBundle
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class ITEDoctrineExtraBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function boot()
    {
        if ($this->container->has('ite_doctrine_extra.proxy_factory')) {
            $proxyFactory = $this->container->get('ite_doctrine_extra.proxy_factory');
            spl_autoload_register($proxyFactory->getProxyAutoloader());
        }

        parent::boot();
    }

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ProxyPass());
    }
}
