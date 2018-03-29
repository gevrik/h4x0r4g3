<?php

/**
 * SystemController Factory.
 * SystemController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Controller\SystemController;
use Netrunners\Service\SystemService;
use Zend\ServiceManager\Factory\FactoryInterface;

class SystemControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return SystemController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SystemController(
            $container->get(EntityManager::class),
            $container->get(SystemService::class)
        );
    }

}
