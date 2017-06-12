<?php

/**
 * BaseService Factory.
 * Factory for the BaseService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\BaseService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class BaseServiceFactory implements FactoryInterface
{

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     *
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new BaseService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('ViewRenderer')
        );
    }

}
