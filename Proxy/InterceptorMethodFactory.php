<?php

namespace ITE\DoctrineExtraBundle\Proxy;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InterceptorMethodFactory
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class InterceptorMethodFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * InterceptorMethodFactory constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return array
     */
    public function createPrefixInterceptorMethods()
    {
        $container = $this->container;

        $methods = $container->getParameter('ite_doctrine_extra.proxy_entity_manager.prefix_interceptors');

        foreach ($methods as &$interceptors) {
            $interceptors = function ($proxy, $em, $method, $parameters, &$returnEarly) use ($container, $interceptors) {
                foreach ($interceptors as $interceptor) {
                    $returnValue = call_user_func_array($interceptor, [$proxy, $em, $method, $parameters, &$returnEarly, $container]);

                    if ($returnEarly) {
                        return $returnValue;
                    }
                }
            };
        }

        return $methods;
    }

    /**
     * @return array
     */
    public function createSuffixInterceptorMethods()
    {
        $container = $this->container;

        $methods = $container->getParameter('ite_doctrine_extra.proxy_entity_manager.suffix_interceptors');

        foreach ($methods as &$interceptors) {
            $interceptors = function ($proxy, $em, $method, $parameters, $returnValue, &$returnEarly) use ($container, $interceptors) {
                foreach ($interceptors as $interceptor) {
                    $returnValue = call_user_func_array($interceptor, [$proxy, $em, $method, $parameters, $returnValue, &$returnEarly, $container]);

                    if ($returnEarly) {
                        return $returnValue;
                    }
                }
            };
        }

        return $methods;
    }
}
