<?php

/**
 * ProfileController Factory.
 * ProfileController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Controller\ProfileController;
use Netrunners\Service\ProfileService;
use Zend\ServiceManager\Factory\FactoryInterface;

class ProfileControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ProfileController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ProfileController(
            $container->get(EntityManager::class),
            $container->get(ProfileService::class)
        );
    }

}
