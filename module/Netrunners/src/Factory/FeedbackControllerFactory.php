<?php

/**
 * FeedbackController Factory.
 * FeedbackController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Controller\FeedbackController;
use Zend\ServiceManager\Factory\FactoryInterface;

class FeedbackControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return FeedbackController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new FeedbackController(
            $container->get(EntityManager::class)
        );
    }

}
