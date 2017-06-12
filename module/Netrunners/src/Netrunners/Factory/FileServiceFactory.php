<?php

/**
 * FileService Factory.
 * Factory for the FileService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\FileService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FileServiceFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new FileService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('ViewRenderer')
        );
    }

}
