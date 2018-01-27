<?php

/**
 * WelcomeService Factory.
 * WelcomeService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use TwistyPassages\Service\WelcomeService;
use Zend\ServiceManager\Factory\FactoryInterface;

class WelcomeServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return WelcomeService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new WelcomeService(
            $container->get(EntityManager::class)
        );
    }

}
