<?php

/**
 * UtilityService Factory.
 * Factory for the UtilityService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\UtilityService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class UtilityServiceFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new UtilityService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('ViewRenderer')
        );
    }

}
