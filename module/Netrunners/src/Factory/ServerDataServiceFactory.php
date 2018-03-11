<?php

/**
 * ServerDataService Factory.
 * ServerDataService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\ServerDataService;
use Netrunners\Service\UtilityService;
use Zend\ServiceManager\Factory\FactoryInterface;

class ServerDataServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return UtilityService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ServerDataService(
            $container->get(EntityManager::class)
        );
    }

}
