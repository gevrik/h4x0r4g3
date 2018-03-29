<?php

/**
 * PassageService Factory.
 * PassageService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use TwistyPassages\Service\PassageService;
use Zend\ServiceManager\Factory\FactoryInterface;

class PassageServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return PassageService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new PassageService(
            $container->get(EntityManager::class)
        );
    }

}
