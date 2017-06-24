<?php

namespace Netrunners\Factory;

use Netrunners\View\Helper\ImageUrlHelper;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ImageUrlHelperFactory implements FactoryInterface
{
    /**
     * Create Service
     * @param ServiceLocatorInterface $serviceLocator
     * @return ImageUrlHelper
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $realServiceLocator = $serviceLocator->getServiceLocator();
        $config = $realServiceLocator->get('config');
        return new ImageUrlHelper($config);
    }
}
