<?php

namespace Netrunners\Factory;

use Interop\Container\ContainerInterface;
use Netrunners\View\Helper\ImageUrlHelper;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImageUrlHelperFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ImageUrlHelper|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ImageUrlHelper(
            $container->get('config')
        );
    }

}
