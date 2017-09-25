<?php

/**
 * FileExecutionService Factory.
 * FileExecutionService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\FileExecutionService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FileExecutionServiceFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new FileExecutionService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('ViewRenderer'),
            $serviceLocator->get('translator'),
            $serviceLocator->get('Netrunners\Service\CodebreakerService'),
            $serviceLocator->get('Netrunners\Service\MissionService'),
            $serviceLocator->get('Netrunners\Service\HangmanService')
        );
    }

}
