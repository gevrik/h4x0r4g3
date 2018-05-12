<?php

/**
 * EntityGenerator Factory.
 * Factory for the EntityGenerator.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\EntityGenerator;
use Zend\ServiceManager\Factory\FactoryInterface;

class EntityGeneratorFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return EntityGenerator
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EntityGenerator(
            $container->get(EntityManager::class)
        );
    }

}
