<?php

/**
 * NotificationService Factory.
 * Factory for the NotificationService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\NotificationService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class NotificationServiceFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new NotificationService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('ViewRenderer')
        );
    }

}
