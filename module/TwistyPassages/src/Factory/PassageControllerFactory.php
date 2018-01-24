<?php

/**
 * PassageController Factory.
 * PassageController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Interop\Container\ContainerInterface;
use TwistyPassages\Controller\PassageController;
use TwistyPassages\Service\PassageService;
use Zend\ServiceManager\Factory\FactoryInterface;

class PassageControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return PassageController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new PassageController(
            $container->get(PassageService::class)
        );
    }

}
