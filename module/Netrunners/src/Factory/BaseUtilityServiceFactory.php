<?php

/**
 * BaseUtilityService Factory.
 * Factory for the BaseUtilityService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\BaseUtilityService;
use Zend\ServiceManager\Factory\FactoryInterface;

class BaseUtilityServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return BaseUtilityService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new BaseUtilityService(
            $container->get(EntityManager::class)
        );
    }

}
