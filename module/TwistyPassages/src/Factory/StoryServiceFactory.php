<?php

/**
 * StoryService Factory.
 * StoryService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use TwistyPassages\Service\StoryService;
use Zend\ServiceManager\Factory\FactoryInterface;

class SystemServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return StoryService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new StoryService(
            $container->get(EntityManager::class)
        );
    }

}
