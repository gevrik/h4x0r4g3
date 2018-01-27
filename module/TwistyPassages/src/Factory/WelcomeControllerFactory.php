<?php

/**
 * WelcomeController Factory.
 * WelcomeController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Interop\Container\ContainerInterface;
use TwistyPassages\Controller\WelcomeController;
use TwistyPassages\Service\WelcomeService;
use Zend\ServiceManager\Factory\FactoryInterface;

class WelcomeControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return WelcomeController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new WelcomeController(
            $container->get(WelcomeService::class)
        );
    }

}
