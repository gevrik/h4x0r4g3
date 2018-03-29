<?php

/**
 * ChoiceService Factory.
 * ChoiceService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use TwistyPassages\Service\ChoiceService;
use Zend\ServiceManager\Factory\FactoryInterface;

class ChoiceServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ChoiceService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ChoiceService(
            $container->get(EntityManager::class)
        );
    }

}
